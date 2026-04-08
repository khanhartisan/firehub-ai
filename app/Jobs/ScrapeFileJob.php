<?php

namespace App\Jobs;

use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use App\Jobs\ScrapeFileJobConcerns\DataPreparingStage;
use App\Jobs\ScrapeFileJobConcerns\EnrichmentStage;
use App\Jobs\ScrapeFileJobConcerns\FetchingStage;
use App\Jobs\ScrapeFileJobConcerns\FinishingStage;
use App\Models\File;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScrapeFileJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use DataPreparingStage;
    use Dispatchable;
    use EnrichmentStage;
    use FetchingStage;
    use FinishingStage;
    use Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    protected File $file;

    protected Lock $manualLock;

    /**
     * Create a new job instance.
     */
    public function __construct(File $file, ?ScrapingStage $stage = null)
    {
        $this->file = $file->withoutRelations();

        if ($stage) {
            $this->updateFileScrapingStage($stage);
        }

        $this->onQueue(QueueEnum::FILE_SCRAPING->value);
    }

    public function uniqueId(): string
    {
        return $this->file->getKey();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $file = File::query()->findOrFail($this->file->getKey());
        $this->file = $file;

        $stage = $file->scraping_stage ?? ScrapingStage::FETCHING;

        // Manual job unique lock
        if (! $lock = $this->getManualLock() or ! $lock->get()) {
            if (env('APP_DEBUG')) {
                dump('Could not acquire lock for file '.$this->uniqueId());
            }

            return;
        }

        // Too many attempts rejection
        if ($file->attempts >= config('queue.max_scrape_attempts')) {
            $this->markFileFailed();

            return;
        }

        // Fetching stage (failure is handled inside handleFileFetchingStage, like ScrapePageJob)
        if ($stage === ScrapingStage::FETCHING) {
            if ($this->handleFileFetchingStage($file)) {
                $this->updateFileScrapingStage(ScrapingStage::DATA_PREPARING);

                $lock->release();
                ScrapeFileJob::dispatch($file);

                return;
            }

            $lock->release();

            return;
        }

        // Data preparing
        elseif ($stage === ScrapingStage::DATA_PREPARING) {
            if ($this->handleFileDataPreparingStage($file)) {
                $this->updateFileScrapingStage(ScrapingStage::ENRICHMENT);

                $lock->release();
                ScrapeFileJob::dispatch($file);

                return;
            }

            $this->markFileFailed();

            return;
        }

        // Enrichment
        elseif ($stage === ScrapingStage::ENRICHMENT) {
            if ($this->handleFileEnrichmentStage($file)) {
                $this->updateFileScrapingStage(ScrapingStage::FINISHING);

                $lock->release();
                ScrapeFileJob::dispatch($file);

                return;
            }

            $this->markFileFailed();

            return;
        }

        // Finishing
        elseif ($stage === ScrapingStage::FINISHING) {
            if ($this->handleFileFinishingStage($file)) {
                $this->markFileSuccess();
                $lock->release();

                return;
            }

            $this->markFileFailed();

            return;
        }

        $lock->release();
    }

    protected function markFileFailed(): void
    {
        $this->file->attempts = intval($this->file->attempts) + 1;
        $this->file->scraping_status = ScrapingStatus::FAILED;
        $this->updateFileScrapingStage(null);

        $this->getManualLock()->release();
    }

    protected function markFileSuccess(): void
    {
        $file = $this->file;
        $file->scraping_status = ScrapingStatus::SUCCESS;
        $file->attempts = 0;
        $this->updateFileScrapingStage(null, false);
        DB::transaction(fn () => $file->save());
    }

    protected function updateFileScrapingStage(?ScrapingStage $stage, bool $save = true): void
    {
        $this->file->scraping_stage = $stage;

        if ($save) {
            $this->file->saveQuietly();
        }

        if (env('APP_DEBUG')) {
            dump('Update file scraping stage to: '.(!$stage ? 'null' : $stage->name));
        }
    }

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(sha1(static::class.'@manual-lock@'.$this->uniqueId()), $this->uniqueFor);
    }
}
