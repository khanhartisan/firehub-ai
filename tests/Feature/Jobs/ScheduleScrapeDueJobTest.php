<?php

namespace Tests\Feature\Jobs;

use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStatus;
use App\Jobs\ScheduleScrapeDueJob;
use App\Jobs\ScrapeFileJob;
use App\Jobs\ScrapePageJob;
use App\Models\File;
use App\Models\Page;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleScrapeDueJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.max_scrape_attempts', 5);
        Config::set('queue.max_scraping_queue_size', 1000);
        Config::set('queue.max_scheduler_queue_size', 100);
    }

    public function test_handle_returns_immediately_when_cache_lock_is_not_acquired(): void
    {
        $outer = Cache::lock('schedule-scrape-due', 300);
        $this->assertTrue($outer->get());

        Queue::fake();

        (new ScheduleScrapeDueJob(50))->handle();

        Queue::assertNothingPushed();

        $outer->release();
    }

    public function test_handle_does_not_redispatch_when_no_files_or_pages_are_due(): void
    {
        Queue::fake();

        (new ScheduleScrapeDueJob(50))->handle();

        Queue::assertNotPushed(ScrapePageJob::class);
        Queue::assertNotPushed(ScrapeFileJob::class);
        Queue::assertNotPushed(ScheduleScrapeDueJob::class);
    }

    public function test_handle_dispatches_scrape_page_job_for_due_page(): void
    {
        Queue::fake();

        $source = Source::query()->create([
            'base_url' => 'https://example.com',
        ]);

        $page = Page::query()->create([
            'source_id' => $source->id,
            'url' => 'https://example.com/due',
            'url_hash' => sha1('https://example.com/due'),
            'scraping_status' => ScrapingStatus::PENDING,
            'next_scrape_at' => now()->subMinute(),
        ]);

        (new ScheduleScrapeDueJob(50))->handle();

        Queue::assertPushedOn(QueueEnum::SCRAPING->value, ScrapePageJob::class, function (ScrapePageJob $job) use ($page): bool {
            return $job->page->id === $page->id;
        });
    }

    public function test_handle_dispatches_scrape_file_job_for_stale_pending_file(): void
    {
        Queue::fake();

        $url = 'https://cdn.example.com/a.jpg';
        $file = File::query()->create([
            'url' => $url,
            'url_hash' => sha1($url),
            'scraping_status' => ScrapingStatus::PENDING,
            'attempts' => 0,
        ]);

        DB::table('files')->where('id', $file->id)->update([
            'updated_at' => now()->subMinutes(10),
        ]);

        (new ScheduleScrapeDueJob(50))->handle();

        Queue::assertPushedTimes(ScrapeFileJob::class, 1);
        Queue::assertPushedOn(QueueEnum::SCRAPING->value, ScrapeFileJob::class);
    }

    public function test_handle_skips_file_when_updated_within_cooldown_window(): void
    {
        Queue::fake();

        $url = 'https://cdn.example.com/recent.jpg';
        $created = File::query()->create([
            'url' => $url,
            'url_hash' => sha1($url),
            'scraping_status' => ScrapingStatus::PENDING,
            'attempts' => 0,
        ]);

        DB::table('files')->where('id', $created->id)->update([
            'updated_at' => now()->subMinute(),
        ]);

        (new ScheduleScrapeDueJob(50))->handle();

        Queue::assertNotPushed(ScrapeFileJob::class);
    }

    public function test_handle_does_not_dispatch_scrape_jobs_when_scraping_queue_has_no_slots(): void
    {
        Config::set('queue.max_scraping_queue_size', 0);

        Queue::fake();

        $source = Source::query()->create([
            'base_url' => 'https://example.org',
        ]);

        Page::query()->create([
            'source_id' => $source->id,
            'url' => 'https://example.org/noslots',
            'url_hash' => sha1('https://example.org/noslots'),
            'scraping_status' => ScrapingStatus::PENDING,
            'next_scrape_at' => now()->subMinute(),
        ]);

        (new ScheduleScrapeDueJob(50))->handle();

        Queue::assertNotPushed(ScrapePageJob::class);
        Queue::assertNotPushed(ScrapeFileJob::class);
        Queue::assertNotPushed(ScheduleScrapeDueJob::class);
    }

    public function test_handle_redispatches_self_when_work_was_scheduled_and_scheduler_has_capacity(): void
    {
        Queue::fake();

        $source = Source::query()->create([
            'base_url' => 'https://redispatch.test',
        ]);

        Page::query()->create([
            'source_id' => $source->id,
            'url' => 'https://redispatch.test/page',
            'url_hash' => sha1('https://redispatch.test/page'),
            'scraping_status' => ScrapingStatus::PENDING,
            'next_scrape_at' => now()->subMinute(),
        ]);

        (new ScheduleScrapeDueJob(25))->handle();

        Queue::assertPushed(ScheduleScrapeDueJob::class, function (ScheduleScrapeDueJob $job): bool {
            return $job->limit === 25;
        });
    }
}
