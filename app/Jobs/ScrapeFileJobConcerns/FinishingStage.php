<?php

namespace App\Jobs\ScrapeFileJobConcerns;

use App\Enums\ScrapingStatus;
use App\Jobs\EmbeddingJob;
use App\Models\File;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait FinishingStage
{
    /**
     * Mark the scrape successful, then run embedding synchronously so the vector is persisted in-line.
     *
     * @return bool True when finishing completed; false to let the job mark the file failed.
     */
    protected function handleFileFinishingStage(File $file): bool
    {
        if (env('APP_DEBUG')) {
            dump('Finishing, file '.$file->id);
        }

        try {
            $this->persistFileScrapeSuccess();
            $embeddingJob = new EmbeddingJob($this->file, true);
            $embeddingJob->handle();
        } catch (\Throwable $e) {
            Log::error("ScrapeFileJob: Finishing failed for file [{$file->id}]: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Persist scrape success so {@see File::isEmbeddable()} is true before {@see EmbeddingJob} runs.
     */
    protected function persistFileScrapeSuccess(): void
    {
        $this->updateFileScrapingStage(null, false);
        $this->file->scraping_status = ScrapingStatus::SUCCESS;
        $this->file->attempts = 0;

        DB::transaction(fn () => $this->file->save());
    }
}
