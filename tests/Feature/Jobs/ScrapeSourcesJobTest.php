<?php

namespace Tests\Feature\Jobs;

use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStatus;
use App\Jobs\ScrapeEntityJob;
use App\Jobs\ScrapeSourcesJob;
use App\Models\Entity;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScrapeSourcesJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('queue.scrape_sources_chunk_size', 10);
        Config::set('queue.scrape_sources_max_seconds', 300);
        Config::set('queue.max_scrape_attempts', 5);
        Config::set('queue.max_scraping_queue_size', 1000);
    }

    public function test_skips_source_when_it_has_planned_scrape_entity(): void
    {
        Bus::fake();

        $source = Source::create([
            'base_url' => 'https://example.com',
        ]);

        $plannedEntity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::PENDING,
            'attempts' => 0,
            'next_scrape_at' => null,
        ]);

        $job = new ScrapeSourcesJob;
        $job->handle();

        Bus::assertNotDispatched(ScrapeEntityJob::class);
        $this->assertDatabaseCount('entities', 1);
        $this->assertDatabaseHas('entities', [
            'id' => $plannedEntity->id,
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::PENDING->value,
        ]);
        $this->assertDatabaseMissing('entities', [
            'source_id' => $source->id,
            'url' => 'https://example.com',
        ]);
    }

    public function test_creates_base_url_entity_and_dispatches_scrape_job_when_source_has_no_planned_entity(): void
    {
        Queue::fake();

        $source = Source::create([
            'base_url' => 'https://example.com/',
        ]);

        $job = new ScrapeSourcesJob;
        $job->handle();

        $this->assertDatabaseCount('entities', 1);
        $entity = Entity::where('source_id', $source->id)->where('url', 'https://example.com/')->first();
        $this->assertNotNull($entity);
        $this->assertSame($source->id, $entity->source_id);
        $this->assertSame('https://example.com/', $entity->url);
        $this->assertSame(sha1('https://example.com/'), $entity->url_hash);
        $this->assertSame(ScrapingStatus::QUEUED->value, $entity->scraping_status->value);

        Queue::assertPushedOn(QueueEnum::SCRAPING->value, ScrapeEntityJob::class, function (ScrapeEntityJob $job) use ($entity): bool {
            return $job->entity->id === $entity->id
                && $job->entity->url === $entity->url
                && $job->entity->source_id === $entity->source_id;
        });
    }

    public function test_dispatches_scrape_job_for_existing_base_url_entity_when_source_has_no_planned_entity(): void
    {
        Queue::fake();

        $source = Source::create([
            'base_url' => 'https://example.com/',
        ]);

        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/',
            'url_hash' => sha1('https://example.com/'),
            'scraping_status' => ScrapingStatus::SUCCESS,
            'next_scrape_at' => now(),
        ]);

        $job = new ScrapeSourcesJob;
        $job->handle();

        $entity->refresh();
        $this->assertDatabaseCount('entities', 1);
        $this->assertSame(ScrapingStatus::QUEUED->value, $entity->scraping_status->value);
        Queue::assertPushedOn(QueueEnum::SCRAPING->value, ScrapeEntityJob::class, function (ScrapeEntityJob $job) use ($entity): bool {
            return $job->entity->id === $entity->id;
        });
    }

    public function test_skips_source_when_base_url_is_empty(): void
    {
        Queue::fake();

        $source = Source::create([
            'base_url' => '',
        ]);

        $job = new ScrapeSourcesJob;
        $job->handle();

        $this->assertDatabaseCount('entities', 0);
        Queue::assertNotPushed(ScrapeEntityJob::class);
    }

    public function test_skips_source_when_base_url_is_invalid(): void
    {
        Queue::fake();

        $source = Source::create([
            'base_url' => 'not-a-url',
        ]);

        $job = new ScrapeSourcesJob;
        $job->handle();

        $this->assertDatabaseCount('entities', 0);
        Queue::assertNotPushed(ScrapeEntityJob::class);
    }

    public function test_does_not_dispatch_when_queue_is_full(): void
    {
        Config::set('queue.max_scraping_queue_size', 0);
        Queue::fake();

        $source = Source::create([
            'base_url' => 'https://example.com',
        ]);

        $job = new ScrapeSourcesJob;
        $job->handle();

        $this->assertDatabaseCount('entities', 1);
        $entity = Entity::where('source_id', $source->id)->where('url', 'https://example.com/')->first();
        $this->assertNotNull($entity);
        $this->assertSame($source->id, $entity->source_id);
        $this->assertSame('https://example.com/', $entity->url);
        $this->assertSame(sha1('https://example.com/'), $entity->url_hash);
        $this->assertSame(ScrapingStatus::PENDING->value, $entity->scraping_status->value);
        $this->assertDatabaseCount('snapshots', 0);
        Queue::assertNotPushed(ScrapeEntityJob::class);
    }

    public function test_processes_sources_in_chunks_ordered_by_updated_at_desc(): void
    {
        Queue::fake();

        $source1 = Source::create(['base_url' => 'https://first.com/']);
        $source1->touch();
        $source2 = Source::create(['base_url' => 'https://second.com/']);

        $job = new ScrapeSourcesJob;
        $job->handle();

        $this->assertDatabaseCount('entities', 2);
        $this->assertDatabaseHas('entities', [
            'source_id' => $source1->id,
            'url' => 'https://first.com/',
            'url_hash' => sha1('https://first.com/'),
            'scraping_status' => ScrapingStatus::QUEUED->value,
        ]);
        $this->assertDatabaseHas('entities', [
            'source_id' => $source2->id,
            'url' => 'https://second.com/',
            'url_hash' => sha1('https://second.com/'),
            'scraping_status' => ScrapingStatus::QUEUED->value,
        ]);
        Queue::assertPushed(ScrapeEntityJob::class, 2);
    }

    public function test_stops_processing_after_timeout(): void
    {
        Config::set('queue.scrape_sources_max_seconds', 0);
        Config::set('queue.scrape_sources_chunk_size', 2);
        Queue::fake();

        Source::create(['base_url' => 'https://one.com']);
        Source::create(['base_url' => 'https://two.com']);
        Source::create(['base_url' => 'https://three.com']);

        $job = new ScrapeSourcesJob;
        $job->handle();

        $this->assertLessThanOrEqual(2, Entity::count());
    }
}
