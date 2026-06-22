<?php

namespace Tests\Unit\Services\ScrapePolicyEngine;

use App\Enums\ScrapingStatus;
use App\Models\Page;
use App\Models\Source;
use App\Services\ScrapePolicyEngine\Drivers\DummyScrapePolicyEngineDriver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapePolicyEngineServiceInitialScrapeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createPage(Source $source, array $overrides = []): Page
    {
        $url = $overrides['url'] ?? 'https://example.com/page-'.uniqid();

        return Page::create(array_merge([
            'source_id' => $source->id,
            'url' => $url,
            'url_hash' => sha1($url),
        ], $overrides));
    }

    public function test_returns_existing_next_scrape_at(): void
    {
        Carbon::setTestNow('2026-03-24 12:00:00');
        /** @var Source $source */
        $source = Source::factory()->create();
        $existing = Carbon::parse('2026-03-25 08:00:00');
        $entity = $this->createPage($source, ['next_scrape_at' => $existing]);

        $driver = new DummyScrapePolicyEngineDriver;

        $this->assertTrue($existing->equalTo($driver->calculateInitialScrapingTime($entity)));
    }

    public function test_no_budgets_schedules_immediately(): void
    {
        Carbon::setTestNow('2026-03-24 12:00:00');
        /** @var Source $source */
        $source = Source::factory()->create();
        $entity = $this->createPage($source);

        $driver = new DummyScrapePolicyEngineDriver;

        $this->assertTrue(now()->equalTo($driver->calculateInitialScrapingTime($entity)));
    }

    public function test_defers_to_next_day_when_daily_budget_full_from_snapshots(): void
    {
        Carbon::setTestNow('2026-03-24 14:00:00');
        /** @var Source $source */
        $source = Source::factory()->create();
        $source->forceFill([
            'daily_budget' => 1,
            'weekly_budget' => 0,
            'monthly_budget' => 0,
        ])->save();
        $other = $this->createPage($source);
        $other->forceFill(['scraped_at' => Carbon::parse('2026-03-24 09:00:00')])->save();

        $newEntity = $this->createPage($source);

        $driver = new DummyScrapePolicyEngineDriver;
        $at = $driver->calculateInitialScrapingTime($newEntity);

        $this->assertTrue($at->equalTo(Carbon::parse('2026-03-25 00:00:00')));
    }

    public function test_defers_to_next_day_when_daily_budget_full_from_in_flight(): void
    {
        Carbon::setTestNow('2026-03-24 14:00:00');
        /** @var Source $source */
        $source = Source::factory()->create();
        $source->forceFill([
            'daily_budget' => 1,
            'weekly_budget' => 0,
            'monthly_budget' => 0,
        ])->save();
        $scheduled = $this->createPage($source);
        $scheduled->forceFill(['next_scrape_at' => Carbon::parse('2026-03-24 10:00:00')])->save();
        $newEntity = $this->createPage($source);

        $driver = new DummyScrapePolicyEngineDriver;
        $at = $driver->calculateInitialScrapingTime($newEntity);

        $this->assertTrue($at->equalTo(Carbon::parse('2026-03-25 00:00:00')));
    }

    public function test_excludes_self_from_in_flight_when_scheduling_same_entity(): void
    {
        Carbon::setTestNow('2026-03-24 14:00:00');
        /** @var Source $source */
        $source = Source::factory()->create();
        $source->forceFill([
            'daily_budget' => 1,
            'weekly_budget' => 0,
            'monthly_budget' => 0,
        ])->save();
        $entity = $this->createPage($source);
        $entity->forceFill(['scraped_at' => Carbon::parse('2026-03-24 10:00:00')])->save();

        $driver = new DummyScrapePolicyEngineDriver;
        $at = $driver->calculateInitialScrapingTime($entity);

        $this->assertTrue(now()->equalTo($at));
    }

    public function test_weekly_cap_defers_past_end_of_iso_week(): void
    {
        Carbon::setTestNow('2026-03-25 12:00:00'); // Wednesday; week starts Monday 2026-03-23
        /** @var Source $source */
        $source = Source::factory()->create();
        $source->forceFill([
            'daily_budget' => 0,
            'weekly_budget' => 1,
            'monthly_budget' => 0,
        ])->save();
        $other = $this->createPage($source);
        $other->forceFill(['scraped_at' => Carbon::parse('2026-03-24 10:00:00')])->save();

        $newEntity = $this->createPage($source);

        $driver = new DummyScrapePolicyEngineDriver;
        $at = $driver->calculateInitialScrapingTime($newEntity);

        $this->assertTrue($at->equalTo(Carbon::parse('2026-03-30 00:00:00')));
    }

    public function test_defers_immediate_schedule_when_priority_backlog_exceeds_queue_capacity(): void
    {
        Carbon::setTestNow('2026-03-24 12:00:00');
        config([
            'queue.max_page_scraping_queue_size' => 2,
            'scrapepolicyengine.priority_backlog_defer_minutes' => 5,
        ]);

        /** @var Source $source */
        $source = Source::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->createPage($source, [
                'url' => 'https://example.com/priority-'.$i,
                'ignore_scraping_budget' => true,
                'scraping_status' => ScrapingStatus::PENDING,
                'next_scrape_at' => now(),
            ]);
        }

        $entity = $this->createPage($source);

        $driver = new DummyScrapePolicyEngineDriver;
        $at = $driver->calculateInitialScrapingTime($entity);

        $this->assertTrue($at->equalTo(Carbon::parse('2026-03-24 12:05:00')));
    }

    public function test_schedules_immediately_when_priority_backlog_fits_queue_capacity(): void
    {
        Carbon::setTestNow('2026-03-24 12:00:00');
        config(['queue.max_page_scraping_queue_size' => 2]);

        /** @var Source $source */
        $source = Source::factory()->create();

        $this->createPage($source, [
            'url' => 'https://example.com/priority-0',
            'ignore_scraping_budget' => true,
            'scraping_status' => ScrapingStatus::QUEUED,
            'next_scrape_at' => now(),
        ]);

        $entity = $this->createPage($source);

        $driver = new DummyScrapePolicyEngineDriver;

        $this->assertTrue(now()->equalTo($driver->calculateInitialScrapingTime($entity)));
    }

    public function test_priority_pages_ignore_backlog_deferral(): void
    {
        Carbon::setTestNow('2026-03-24 12:00:00');
        config(['queue.max_page_scraping_queue_size' => 1]);

        /** @var Source $source */
        $source = Source::factory()->create();

        $this->createPage($source, [
            'url' => 'https://example.com/priority-0',
            'ignore_scraping_budget' => true,
            'scraping_status' => ScrapingStatus::FETCHING,
            'next_scrape_at' => now(),
        ]);

        $priorityPage = $this->createPage($source, [
            'url' => 'https://example.com/priority-1',
            'ignore_scraping_budget' => true,
            'scraping_status' => ScrapingStatus::PENDING,
            'next_scrape_at' => now(),
        ]);

        $driver = new DummyScrapePolicyEngineDriver;

        $this->assertTrue(now()->equalTo($driver->calculateInitialScrapingTime($priorityPage)));
    }

    public function test_budget_deferred_time_is_not_pushed_by_priority_backlog(): void
    {
        Carbon::setTestNow('2026-03-24 14:00:00');
        config(['queue.max_page_scraping_queue_size' => 1]);

        /** @var Source $source */
        $source = Source::factory()->create();
        $source->forceFill([
            'daily_budget' => 1,
            'weekly_budget' => 0,
            'monthly_budget' => 0,
        ])->save();

        $this->createPage($source, [
            'url' => 'https://example.com/priority-0',
            'ignore_scraping_budget' => true,
            'scraping_status' => ScrapingStatus::PROCESSING,
            'next_scrape_at' => now(),
        ]);
        $this->createPage($source, [
            'url' => 'https://example.com/priority-1',
            'ignore_scraping_budget' => true,
            'scraping_status' => ScrapingStatus::PENDING,
            'next_scrape_at' => now(),
        ]);

        $other = $this->createPage($source);
        $other->forceFill(['scraped_at' => Carbon::parse('2026-03-24 09:00:00')])->save();

        $newEntity = $this->createPage($source);

        $driver = new DummyScrapePolicyEngineDriver;
        $at = $driver->calculateInitialScrapingTime($newEntity);

        $this->assertTrue($at->equalTo(Carbon::parse('2026-03-25 00:00:00')));
    }
}
