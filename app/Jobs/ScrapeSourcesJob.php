<?php

namespace App\Jobs;

use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStatus;
use App\Models\Entity;
use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ScrapeSourcesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum seconds the job may run (worker will kill after this).
     * Slightly above scrape_sources_max_seconds so we normally exit before being killed.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(QueueEnum::SCHEDULER->value);
        $maxSeconds = config('queue.scrape_sources_max_seconds');
        $this->timeout = $maxSeconds + 60;
    }

    /**
     * Execute the job: process sources in chunks (order by updated_at),
     * with a timeout so we stop after max seconds. For each source with no planned
     * scrape entity, ensure an entity exists for its base_url and dispatch ScrapeEntityJob.
     */
    public function handle(): void
    {
        $startTime = time();
        $maxSeconds = config('queue.scrape_sources_max_seconds');
        $chunkSize = config('queue.scrape_sources_chunk_size');

        Source::query()
            ->orderBy('updated_at')
            ->chunk($chunkSize, function ($sources) use ($startTime, $maxSeconds): bool {
                if (time() - $startTime >= $maxSeconds) {
                    return false;
                }

                foreach ($sources as $source) {
                    $this->processSource($source);
                }

                return true;
            });
    }

    protected function processSource(Source $source): void
    {
        if ($this->sourceHasPlannedScrapeEntity($source)) {
            return;
        }

        $baseUrl = $this->normalizeBaseUrl($source->base_url ?? '');
        if ($baseUrl === '') {
            return;
        }

        $entity = Entity::query()
            ->where('source_id', $source->id)
            ->where('url_hash', sha1($baseUrl))
            ->first();

        if ($entity === null) {
            $entity = Entity::query()->create([
                'source_id' => $source->id,
                'url' => $baseUrl,
            ]);
        }

        if (! QueueEnum::SCRAPING->canDispatch()) {
            return;
        }

        $entity->scraping_status = ScrapingStatus::QUEUED;
        DB::transaction(fn () => $entity->save());

        ScrapeEntityJob::dispatch($entity)->onQueue(QueueEnum::SCRAPING->value);
    }

    protected function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://'))) {
            return '';
        }

        return $url;
    }

    /**
     * Whether the source has at least one entity that is due for scraping (planned).
     */
    protected function sourceHasPlannedScrapeEntity(Source $source): bool
    {
        $maxAttempts = config('queue.max_scrape_attempts');

        return Entity::query()
            ->where('source_id', $source->id)
            ->where('scraping_status', ScrapingStatus::PENDING)
            ->where('attempts', '<', $maxAttempts)
            ->where(function ($query) {
                $query->whereNull('next_scrape_at')
                    ->orWhere('next_scrape_at', '<=', now());
            })
            ->exists();
    }
}
