<?php

namespace App\Jobs;

use App\Contracts\PageParser\PageData;
use App\Enums\PageType;
use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use App\Facades\ScrapePolicyEngine;
use App\Jobs\Concerns\HasManualLock;
use App\Jobs\ScrapePageJobConcerns\DataParsingStage;
use App\Jobs\ScrapePageJobConcerns\DataPreparingStage;
use App\Jobs\ScrapePageJobConcerns\EnrichmentStage;
use App\Jobs\ScrapePageJobConcerns\ExpandingStage;
use App\Jobs\ScrapePageJobConcerns\FetchingStage;
use App\Jobs\ScrapePageJobConcerns\FileEnrichmentStage;
use App\Jobs\ScrapePageJobConcerns\FinishingStage;
use App\Jobs\ScrapePageJobConcerns\PolicyEvaluationStage;
use App\Jobs\ScrapePageJobConcerns\VerticalResolutionStage;
use App\Models\Page;
use App\Models\Snapshot;
use App\Utils\Debugger;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScrapePageJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use HasManualLock;
    use DataParsingStage;
    use DataPreparingStage;
    use Dispatchable;
    use EnrichmentStage;
    use FileEnrichmentStage;
    use ExpandingStage;
    use FetchingStage;
    use FinishingStage;
    use InteractsWithQueue;
    use PolicyEvaluationStage;
    use Queueable;
    use SerializesModels;
    use VerticalResolutionStage;

    /**
     * Delete the job if the page no longer exists.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Maximum number of times to attempt the job.
     */
    public int $tries = 2;

    /**
     * Number of seconds to wait before retrying after a failure.
     */
    public int $backoff = 300;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    /**
     * The page to scrape.
     */
    public Page $page;

    /**
     * Create a new job instance.
     */
    public function __construct(Page $page, ?ScrapingStage $stage = null)
    {
        $this->page = $page->withoutRelations();

        if ($stage) {
            $this->updatePageScrapingStage($stage);
        }

        $this->onQueue(QueueEnum::PAGE_SCRAPING->value);
    }

    public function uniqueId(): string
    {
        return $this->page->getKey();
    }

    public function handle(): void
    {
        try {
            $this->_handle();
        } catch (Exception $e) {
            $this->markPageFailed();

            $logs = $this->page->error_logs ?? '';
            $logs .= "\n---\n".$e->getMessage()."\n---\n";
            if ($e instanceof GuzzleException
                and method_exists($e, 'getResponse')
                and $errorResponseBody = $e->getResponse()->getBody()->getContents()
            ) {
                $logs .= $errorResponseBody."\n---\n";
            }
            $logs .= $e->getTraceAsString();

            // Trim the logs
            if (strlen($logs) > 10000) {
                $logs = '...trimmed...'."\n".substr($logs, -10000);
            }

            // Log the exception for debugging
            Page::query()->where('id', $this->page->id)->update([
                'error_logs' => $logs,
            ]);
        }
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function _handle(): void
    {
        $page = $this->page;

        // Skip if not at scrape time yet
        if (!$page->ignore_scraping_budget
            and $page->next_scrape_at?->gt(now())
        ) {
            Debugger::devConsoleDump('Page '.$page->id.'. Not scrape time yet. Next scrape time: '.$page->next_scrape_at->diffForHumans().'. Skipping...');
            return;
        }

        $stage = $page->scraping_stage ?? ScrapingStage::FETCHING;

        // Manual job unique lock
        if (! $lock = $this->getManualLock() or ! $lock->get()) {
            Debugger::devConsoleDump('Could not acquire lock for '.$this->uniqueId());

            return;
        }

        // Reject if 2 many attempts
        if ($page->attempts >= config('queue.max_scrape_attempts')) {
            $this->markPageFailed();
            return;
        }

        // Check budget
        // If budget is exceeded, push the job to the next window
        if ($stage === ScrapingStage::FETCHING
            and $initialScrapingTime = ScrapePolicyEngine::calculateInitialScrapingTime($page)
            and $initialScrapingTime->gt(now())
        ) {
            $page->scraping_status = ScrapingStatus::PENDING;
            $page->next_scrape_at = $initialScrapingTime;
            DB::transaction(fn () => $page->save());
            $lock->release();

            Debugger::devConsoleDump('Budget exceeded (Page '.$page->id.'). Pushed to '.$initialScrapingTime->diffForHumans());

            return;
        }

        // Fetching stage
        if ($stage === ScrapingStage::FETCHING) {
            if ($snapshot = $this->handleFetchingStage($page)) {

                // Mark as finished if the data size is too large
                // Or data type isn't supported
                if ($snapshot->file_size >= 10 * 1024 * 1024
                    or ! in_array($snapshot->file_extension, [
                        'html', 'txt',
                        'jpeg', 'jpg', 'png', 'webp', 'avif', 'gif', 'bmp', 'tiff',
                    ])
                ) {
                    $this->markPageSuccess();
                    return;
                }

                // Mark scraping status as PROCESSING
                $page->scraping_status = ScrapingStatus::PROCESSING;
                $page->scraped_at = Carbon::now();
                $this->updatePageScrapingStage(ScrapingStage::DATA_PREPARING, false);
                DB::transaction(fn () => $page->save());

                // Continue to prepare the data
                $lock->release();
                ScrapePageJob::dispatch($page);
            }
        }

        // Data preparing
        elseif ($stage === ScrapingStage::DATA_PREPARING) {

            // Data preparation was rejected
            if (! $this->handleDataPreparingStage($page)) {
                $this->markPageFailed();
                return;
            }

            // Continue to parse data if text
            if (in_array($page->currentSnapshot?->file_extension, ['html', 'txt'])) {

                $this->updatePageScrapingStage(ScrapingStage::DATA_PARSING);
                $lock->release();

                ScrapePageJob::dispatch($page);

                return;
            }

            // Continue to enrichment
            $this->updatePageScrapingStage(ScrapingStage::ENRICHMENT);
            $lock->release();

            ScrapePageJob::dispatch($page);
        }

        // Data parsing
        elseif ($stage === ScrapingStage::DATA_PARSING) {

            // Failed to parse
            if (! $this->handleDataParsingStage($page)) {
                $this->markPageFailed();
                return;
            }

            // Continue to enrichment
            $this->updatePageScrapingStage(ScrapingStage::ENRICHMENT);
            $lock->release();
            ScrapePageJob::dispatch($page);
        }

        // Enrichment stage
        elseif ($stage === ScrapingStage::ENRICHMENT) {

            // Failed to enrich
            if (! $this->handleEnrichmentStage($page)) {
                $this->markPageFailed();
                return;
            }

            // Continue to policy evaluation if not detail page
            if ($page->page_type !== PageType::DETAIL) {
                $this->updatePageScrapingStage(ScrapingStage::POLICY_EVALUATION);
            }
            // Continue to file enrichment
            else {
                $this->updatePageScrapingStage(ScrapingStage::FILE_ENRICHMENT);
            }

            $lock->release();
            ScrapePageJob::dispatch($page);
        }

        // File enrichment stage
        elseif ($stage === ScrapingStage::FILE_ENRICHMENT) {

            $fileEnrichmentResult = $this->handleFileEnrichmentStage($page);

            // If return null, we wait
            if (is_null($fileEnrichmentResult)) {
                $lock->release();
                ScrapePageJob::dispatch($page)->delay(10);
                Debugger::devConsoleDump('Awaiting file enrichment...');
                return;
            }

            // Failed
            if (!$fileEnrichmentResult) {
                $this->markPageFailed();
                return;
            }

            // Continue to vertical resolution
            $this->updatePageScrapingStage(ScrapingStage::VERTICAL_RESOLUTION);
            $lock->release();
            ScrapePageJob::dispatch($page);
        }

        // Vertical resolution stage
        elseif ($stage === ScrapingStage::VERTICAL_RESOLUTION) {

            // Failed to resolve
            if (! $this->handleVerticalResolutionStage($page)) {
                $this->markPageFailed();
                return;
            }

            // Continue to policy evaluation
            $this->updatePageScrapingStage(ScrapingStage::POLICY_EVALUATION);
            $lock->release();
            ScrapePageJob::dispatch($page);
        }

        // Policy evaluation
        elseif ($stage === ScrapingStage::POLICY_EVALUATION) {

            // Failed to evaluate
            if (! $this->handlePolicyEvaluationStage($page)) {
                $this->markPageFailed();
                return;
            }

            // Continue to expand
            $this->updatePageScrapingStage(ScrapingStage::EXPANDING);
            $lock->release();
            ScrapePageJob::dispatch($page);
        }

        // Expanding
        elseif ($stage === ScrapingStage::EXPANDING) {
            try {
                $this->handleExpandingStage($page);
            } finally {

                // Continue to finish
                $this->updatePageScrapingStage(ScrapingStage::FINISHING);
                $lock->release();
                ScrapePageJob::dispatch($page);
            }
        }

        // Finishing
        elseif ($stage === ScrapingStage::FINISHING) {

            // Failed to finish
            if (! $this->handleFinishingStage($page)) {
                $this->markPageFailed();

                return;
            }

            $this->markPageSuccess();
        }

        $lock->release();
    }

    /**
     * @throws Exception
     */
    protected function markPageSuccess(): void
    {
        $page = $this->page;
        $this->updatePageScrapingStage(null, false);
        $page->ignore_scraping_budget = false;
        $page->scraping_status = ScrapingStatus::SUCCESS;
        $page->attempts = 0;
        DB::transaction(fn () => $page->save());

        $this->getManualLock()->release();
    }

    /**
     * @throws Exception
     */
    protected function markPageFailed(): void
    {
        $page = $this->page;
        $this->updatePageScrapingStage(null, false);
        $page->attempts = $page->attempts + 1;

        // Stop scraping if too many attempts
        $maxAttempts = config('queue.max_scrape_attempts');
        if ($page->attempts >= $maxAttempts) {
            Debugger::devConsoleDump('Page '.$page->id.'. Too many attempts: '.$page->attempts.'/'.$maxAttempts);

            $page->ignore_scraping_budget = false;
            $page->next_scrape_at = null;
            $page->scraping_status = ScrapingStatus::FAILED;
        } else {
            $page->next_scrape_at = $page->ignore_scraping_budget
                ? now()
                : ScrapePolicyEngine::calculateInitialScrapingTime($page);
            $page->scraping_status = ScrapingStatus::PENDING;
        }

        DB::transaction(fn () => $page->save());

        $this->getManualLock()->release();
    }

    protected function updatePageScrapingStage(?ScrapingStage $stage, bool $save = true): void
    {
        $page = $this->page;
        $page->scraping_stage = $stage;

        if ($save) {
            $page->saveQuietly();
        }
    }
}
