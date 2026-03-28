<?php

namespace App\Jobs;

use App\Contracts\VerticalResolver\Vertical as ContractVertical;
use App\Enums\EntityType;
use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use App\Facades\PageClassifier;
use App\Facades\PageParser;
use App\Facades\ScrapePolicyEngine;
use App\Facades\VerticalResolver as VerticalResolverFacade;
use App\Facades\Scraper;
use App\Jobs\ScrapeEntityJobConcerns\EnrichmentStage;
use App\Jobs\ScrapeEntityJobConcerns\DataParsingStage;
use App\Jobs\ScrapeEntityJobConcerns\DataPreparingStage;
use App\Jobs\ScrapeEntityJobConcerns\ExpandingStage;
use App\Jobs\ScrapeEntityJobConcerns\FetchingStage;
use App\Jobs\ScrapeEntityJobConcerns\FinishingStage;
use App\Jobs\ScrapeEntityJobConcerns\PolicyEvaluationStage;
use App\Jobs\ScrapeEntityJobConcerns\VerticalResolutionStage;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Source;
use App\Models\Vertical as VerticalModel;
use App\Models\Tag;
use App\Utils\HtmlCleaner;
use App\Utils\Json;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

class ScrapeEntityJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    use FetchingStage;
    use DataPreparingStage;
    use DataParsingStage;
    use EnrichmentStage;
    use VerticalResolutionStage;
    use PolicyEvaluationStage;
    use FinishingStage;
    use ExpandingStage;

    /**
     * Delete the job if the entity no longer exists.
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
     * The entity to scrape.
     */
    public Entity $entity;

    protected Lock $manualLock;

    /**
     * Create a new job instance.
     */
    public function __construct(Entity $entity, ?ScrapingStage $stage = null)
    {
        $this->entity = $entity->withoutRelations();

        if ($stage) {
            $this->updateEntityScrapingStage($stage);
        }

        $this->onQueue(QueueEnum::SCRAPING->value);
    }

    public function uniqueId(): string
    {
        return $this->entity->getKey();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $entity = $this->entity;

        $stage = $entity->scraping_stage ?? ScrapingStage::FETCHING;

        // Manual job unique lock
        if (!$lock = $this->getManualLock() or !$lock->get()) {
            if (env('APP_DEBUG')) {
                dump('Could not acquire lock for '.$this->uniqueId());
            }
            return;
        }

        // Reject if 2 many attempts
        if ($entity->attempts >= config('queue.max_scrape_attempts')) {
            $this->markEntityFailed();
            return;
        }

        // Check budget
        // If budget is exceeded, push the job to the next window
        if ($initialScrapingTime = ScrapePolicyEngine::calculateInitialScrapingTime($entity)
            and $initialScrapingTime->gt(now())
        ) {
            $entity->scraping_status = ScrapingStatus::PENDING;
            $entity->next_scrape_at = $initialScrapingTime;
            DB::transaction(fn () => $entity->save());
            $lock->release();

            if (env('APP_DEBUG')) {
                dump('Budget exceeded (Entity '.$entity->id.'). Pushed to '.$initialScrapingTime->diffForHumans());
            }

            return;
        }

        // Fetching stage
        if ($stage === ScrapingStage::FETCHING) {
            if ($snapshot = $this->handleFetchingStage($entity)) {

                // Mark as finished if the data size is too large
                // Or data type isn't supported
                if ($snapshot->file_size >= 10 * 1024 * 1024
                    or !in_array($snapshot->file_extension, [
                        'html', 'txt',
                        'jpeg', 'jpg', 'png', 'webp', 'avif', 'gif', 'bmp', 'tiff',
                    ])
                ) {
                    $this->markEntitySuccess();
                    return;
                }

                // Mark scraping status as PROCESSING
                $entity->scraping_status = ScrapingStatus::PROCESSING;
                $entity->scraped_at = Carbon::now();
                $this->updateEntityScrapingStage(ScrapingStage::DATA_PREPARING, false);
                DB::transaction(fn () => $entity->save());

                // Continue to prepare the data
                $lock->release();
                ScrapeEntityJobDispatcher::dispatch($entity);
            }
        }

        // Data preparing
        elseif ($stage === ScrapingStage::DATA_PREPARING) {

            // Data preparation was rejected
            if (!$this->prepareData($entity)) {
                $this->markEntityFailed();
                return;
            }

            // Continue to parse data if text
            if (in_array($entity->currentSnapshot?->file_extension, ['html', 'txt'])) {

                $this->updateEntityScrapingStage(ScrapingStage::DATA_PARSING);
                $lock->release();

                ScrapeEntityJobDispatcher::dispatch($entity);
                return;
            }

            // Continue to enrichment
            $this->updateEntityScrapingStage(ScrapingStage::ENRICHMENT);
            $lock->release();

            ScrapeEntityJobDispatcher::dispatch($entity);
        }

        // Data parsing
        elseif ($stage === ScrapingStage::DATA_PARSING) {

            // Failed to parse
            if (!$this->parseData($entity)) {
                $this->markEntityFailed();
                return;
            }

            // Continue to enrichment
            $this->updateEntityScrapingStage(ScrapingStage::ENRICHMENT);
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch($entity);
        }

        // Enrichment stage
        elseif ($stage === ScrapingStage::ENRICHMENT) {

            // Failed to enrich
            if (!$this->enrich($entity)) {
                $this->markEntityFailed();
                return;
            }

            // Continue to vertical resolution
            $this->updateEntityScrapingStage(ScrapingStage::VERTICAL_RESOLUTION);
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch($entity);
        }

        // Vertical resolution stage
        elseif ($stage === ScrapingStage::VERTICAL_RESOLUTION) {

            // Failed to resolve
            if (!$this->verticalResolve($entity)) {
                $this->markEntityFailed();
                return;
            }

            // Continue to policy evaluation
            $this->updateEntityScrapingStage(ScrapingStage::POLICY_EVALUATION);
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch($entity);
        }

        // Policy evaluation
        elseif ($stage === ScrapingStage::POLICY_EVALUATION) {

            // Failed to evaluate
            if (!$this->evaluatePolicy($entity)) {
                $this->markEntityFailed();
                return;
            }

            // Continue to expand
            $this->updateEntityScrapingStage(ScrapingStage::EXPANDING);
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch($entity);
        }

        // Expanding
        elseif ($stage === ScrapingStage::EXPANDING) {
            try {
                $this->expand($entity);
            } finally {

                // Continue to finish
                $this->updateEntityScrapingStage(ScrapingStage::FINISHING);
                $lock->release();
                ScrapeEntityJobDispatcher::dispatch($entity);
            }
        }

        // Finishing
        elseif ($stage === ScrapingStage::FINISHING) {

            // Failed to finish
            if (!$this->finish($entity)) {
                $this->markEntityFailed();
                return;
            }

            $this->markEntitySuccess();
        }

        $lock->release();
    }

    protected function markEntitySuccess(): void
    {
        $entity = $this->entity;
        $this->updateEntityScrapingStage(null, false);
        $entity->scraping_status = ScrapingStatus::SUCCESS;
        $entity->attempts = 0;
        DB::transaction(fn () => $entity->save());

        $this->getManualLock()->release();
    }

    protected function markEntityFailed(): void
    {
        $entity = $this->entity;
        $this->updateEntityScrapingStage(null, false);
        $entity->scraping_status = ScrapingStatus::FAILED;
        $entity->attempts = $entity->attempts + 1;

        // Stop scraping if too many attempts
        if ($entity->attempts >= config('queue.max_scrape_attempts')) {
            $entity->next_scrape_at = null;
        } else {
            $entity->next_scrape_at = ScrapePolicyEngine::calculateInitialScrapingTime($entity);
        }

        DB::transaction(fn () => $entity->save());

        $this->getManualLock()->release();
    }

    protected function updateEntityScrapingStage(?ScrapingStage $stage, bool $save = true): void
    {
        $entity = $this->entity;
        $entity->scraping_stage = $stage;

        if ($save) {
            $entity->saveQuietly();
        }
    }

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(sha1(static::class.'@manual-lock@'.$this->uniqueId()), $this->uniqueFor);
    }
}
