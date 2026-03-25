<?php

namespace Tests\Unit\Services\ScrapePolicyEngine;

use App\Models\Entity;
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

    private function createEntity(Source $source, array $overrides = []): Entity
    {
        $url = $overrides['url'] ?? 'https://example.com/page-'.uniqid();

        return Entity::create(array_merge([
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
        $entity = $this->createEntity($source, ['next_scrape_at' => $existing]);

        $driver = new DummyScrapePolicyEngineDriver;

        $this->assertTrue($existing->equalTo($driver->calculateInitialScrapingTime($entity)));
    }

    public function test_no_budgets_schedules_immediately(): void
    {
        Carbon::setTestNow('2026-03-24 12:00:00');
        /** @var Source $source */
        $source = Source::factory()->create();
        $entity = $this->createEntity($source);

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
        $other = $this->createEntity($source);
        $other->forceFill(['scraped_at' => Carbon::parse('2026-03-24 09:00:00')])->save();

        $newEntity = $this->createEntity($source);

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
        $scheduled = $this->createEntity($source);
        $scheduled->forceFill(['next_scrape_at' => Carbon::parse('2026-03-24 10:00:00')])->save();
        $newEntity = $this->createEntity($source);

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
        $entity = $this->createEntity($source);
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
        $other = $this->createEntity($source);
        $other->forceFill(['scraped_at' => Carbon::parse('2026-03-24 10:00:00')])->save();

        $newEntity = $this->createEntity($source);

        $driver = new DummyScrapePolicyEngineDriver;
        $at = $driver->calculateInitialScrapingTime($newEntity);

        $this->assertTrue($at->equalTo(Carbon::parse('2026-03-30 00:00:00')));
    }
}
