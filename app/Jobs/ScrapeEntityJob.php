<?php

namespace App\Jobs;

use App\Contracts\VerticalResolver\Vertical as ContractVertical;
use App\Enums\EntityType;
use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapeEntityJobStage;
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
    public function __construct(Entity $entity, protected ScrapeEntityJobStage $stage = ScrapeEntityJobStage::FETCHING)
    {
        $this->entity = $entity->withoutRelations();
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

        $stage = $this->stage;

        // Manual job unique lock
        if (!$lock = $this->getManualLock() or !$lock->get()) {
            if (env('APP_DEBUG')) {
                dump('Could not acquire lock for '.$this->uniqueId());
            }
            return;
        }

        // Reject if 2 many attempts
        if ($entity->attempts >= config('queue.max_scrape_attempts')) {
            $this->markEntityFailed($entity);
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
        if ($stage === ScrapeEntityJobStage::FETCHING) {
            if ($snapshot = $this->handleFetchingStage($entity)) {

                // Mark as finished if the data size is too large
                // Or data type isn't supported
                if ($snapshot->file_size >= 10 * 1024 * 1024
                    or !in_array($snapshot->file_extension, [
                        'html', 'txt',
                        'jpeg', 'jpg', 'png', 'webp', 'avif', 'gif', 'bmp', 'tiff',
                    ])
                ) {
                    $this->markEntitySuccess($entity);
                    return;
                }

                // Mark scraping status as PROCESSING
                $entity->scraping_status = ScrapingStatus::PROCESSING;
                $entity->scraped_at = Carbon::now();
                DB::transaction(fn () => $entity->save());

                // Continue to prepare the data
                $lock->release();
                ScrapeEntityJobDispatcher::dispatch(
                    $entity,
                    ScrapeEntityJobStage::DATA_PREPARING
                );
            }
        }

        // Data preparing
        elseif ($stage === ScrapeEntityJobStage::DATA_PREPARING) {

            // Data preparation was rejected
            if (!$this->prepareData($entity)) {
                $this->markEntityFailed($entity);
                return;
            }

            // Continue to parse data if text
            if (in_array($entity->currentSnapshot?->file_extension, ['html', 'txt'])) {
                $lock->release();
                ScrapeEntityJobDispatcher::dispatch(
                    $entity,
                    ScrapeEntityJobStage::DATA_PARSING
                );
                return;
            }

            // Continue to enrichment
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch(
                $entity,
                ScrapeEntityJobStage::ENRICHMENT
            );
        }

        // Data parsing
        elseif ($stage === ScrapeEntityJobStage::DATA_PARSING) {

            // Failed to parse
            if (!$this->parseData($entity)) {
                $this->markEntityFailed($entity);
                return;
            }

            // Continue to enrichment
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch(
                $entity,
                ScrapeEntityJobStage::ENRICHMENT
            );
        }

        // Enrichment stage
        elseif ($stage === ScrapeEntityJobStage::ENRICHMENT) {

            // Failed to enrich
            if (!$this->enrich($entity)) {
                $this->markEntityFailed($entity);
                return;
            }

            // Continue to vertical resolution
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch(
                $entity,
                ScrapeEntityJobStage::VERTICAL_RESOLUTION
            );
        }

        // Vertical resolution stage
        elseif ($stage === ScrapeEntityJobStage::VERTICAL_RESOLUTION) {

            // Failed to resolve
            if (!$this->verticalResolve($entity)) {
                $this->markEntityFailed($entity);
                return;
            }

            // Continue to policy evaluation
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch(
                $entity,
                ScrapeEntityJobStage::POLICY_EVALUATION
            );
        }

        // Policy evaluation
        elseif ($stage === ScrapeEntityJobStage::POLICY_EVALUATION) {

            // Failed to evaluate
            if (!$this->evaluatePolicy($entity)) {
                $this->markEntityFailed($entity);
                return;
            }

            // Continue to expand
            $lock->release();
            ScrapeEntityJobDispatcher::dispatch(
                $entity,
                ScrapeEntityJobStage::EXPANDING
            );
        }

        // Expanding
        elseif ($stage === ScrapeEntityJobStage::EXPANDING) {
            try {
                $this->expand($entity);
            } finally {

                // Continue to finish
                $lock->release();
                ScrapeEntityJobDispatcher::dispatch(
                    $entity,
                    ScrapeEntityJobStage::FINISHING
                );
            }
        }

        // Finishing
        elseif ($stage === ScrapeEntityJobStage::FINISHING) {

            // Failed to finish
            if (!$this->finish($entity)) {
                $this->markEntityFailed($entity);
                return;
            }
        }

        $lock->release();
    }

    protected function markEntitySuccess(Entity $entity): void
    {
        $entity->scraping_status = ScrapingStatus::SUCCESS;
        $entity->attempts = 0;
        DB::transaction(fn () => $entity->save());

        $this->getManualLock()->release();
    }

    protected function markEntityFailed(Entity $entity): void
    {
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

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(sha1(static::class.'@manual-lock@'.$this->uniqueId()), $this->uniqueFor);
    }
}
