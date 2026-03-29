<?php

namespace App\Jobs;

use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStatus;
use App\Models\Page;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ScheduleScrapeDueJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Lock key: only one execution at a time, regardless of worker count.
     */
    private const string LOCK_KEY = 'schedule-scrape-due';

    /**
     * Max seconds to hold the lock (safety in case job crashes without releasing).
     */
    private const int LOCK_SECONDS = 300;

    /**
     * Number of seconds after which the unique lock will be released (e.g. if job fails).
     */
    public int $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $limit = 50)
    {
        $this->onQueue(QueueEnum::SCHEDULER->value);
    }

    /**
     * The unique ID of the job. Only one instance can be in the queue at a time,
     * so the queue is never spammed (schedule or duplicate workers).
     */
    public function uniqueId(): string
    {
        return 'schedule-scrape-due';
    }

    /**
     * Execute the job: queue due pages, then re-dispatch self immediately.
     * ShouldBeUniqueUntilProcessing: only one job in queue at a time (no spam).
     * In-job Cache::lock: only one execution at a time (no race with multiple workers).
     */
    public function handle(): void
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (!$lock->get()) {
            return;
        }

        try {
            if ($this->runScheduler() and QueueEnum::SCHEDULER->canDispatch()) {
                static::dispatch($this->limit)->delay(now()->addSecond());
            }
        } finally {
            $lock->release();
        }
    }

    private function runScheduler(): int
    {
        $dispatched = 0;

        // Dispatch jobs
        foreach (ScrapingStatus::cases() as $scrapingStatus) {
            $query = Page::query()
                ->where('scraping_status', $scrapingStatus)
                ->whereNotNull('next_scrape_at')
                ->where('next_scrape_at', '<=', now())
                ->orderBy('next_scrape_at');

            $dispatched += $this->dispatchScrapePageJobs($query);
        }

        return $dispatched;
    }

    /**
     * @param Builder $pageQuery
     * @return int The number of pages dispatched
     */
    private function dispatchScrapePageJobs(Builder $pageQuery): int
    {
        $queue = QueueEnum::SCRAPING;
        $queueName = $queue->value;

        $slotsAvailable = $queue->slotsAvailable();
        if ($slotsAvailable <= 0) {
            return 0;
        }

        $toDispatch = min($this->limit, $slotsAvailable);

        $pages = $pageQuery->take($toDispatch)->get();

        if ($pages->isEmpty()) {
            return 0;
        }

        $ids = $pages->pluck('id')->toArray();
        DB::transaction(function () use ($ids) {
            Page::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->each(function (Page $page) {
                    $page->scraping_status  = ScrapingStatus::QUEUED;
                    $page->save();
                });
        });

        foreach ($pages as $page) {
            ScrapePageJob::dispatch($page)->onQueue($queueName);
        }

        $maxQueueSize = $queue->maxSize();
        $currentSize = Queue::size($queueName);
        Log::debug('ScheduleScrapeDueJob: Queued '.$pages->count().' pages for scraping.', [
            'queue' => $currentSize.' → '.($currentSize + $pages->count()).'/'.$maxQueueSize,
        ]);

        return $pages->count();
    }
}
