<?php

namespace Tests\Feature\Jobs;

use App\Enums\ScrapingStatus;
use App\Jobs\SetInitialScrapingTimeJob;
use App\Models\Entity;
use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetInitialScrapingTimeJobTest extends TestCase
{
    use RefreshDatabase;

    private function createEntity(Source $source, array $overrides = []): Entity
    {
        $url = $overrides['url'] ?? 'https://example.com/page-'.uniqid();

        return Entity::create(array_merge([
            'source_id' => $source->id,
            'url' => $url,
            'url_hash' => sha1($url),
            'scraping_status' => ScrapingStatus::PENDING,
            'next_scrape_at' => null,
        ], $overrides));
    }

    public function test_sets_next_scrape_at_for_pending_entity_when_null(): void
    {
        Carbon::setTestNow('2026-04-01 10:00:00');
        $source = Source::factory()->create();
        $entity = $this->createEntity($source);

        (new SetInitialScrapingTimeJob)->handle();

        $entity->refresh();
        $this->assertNotNull($entity->next_scrape_at);
        $this->assertTrue($entity->next_scrape_at->equalTo(now()));

        Carbon::setTestNow();
    }

    public function test_skips_failed_entities(): void
    {
        Carbon::setTestNow('2026-04-01 10:00:00');
        $source = Source::factory()->create();
        $entity = $this->createEntity($source, [
            'scraping_status' => ScrapingStatus::FAILED,
        ]);

        (new SetInitialScrapingTimeJob)->handle();

        $entity->refresh();
        $this->assertNull($entity->next_scrape_at);

        Carbon::setTestNow();
    }

    public function test_does_not_select_entities_that_already_have_next_scrape_at(): void
    {
        Carbon::setTestNow('2026-04-01 10:00:00');
        $source = Source::factory()->create();
        $scheduled = Carbon::parse('2026-04-05 12:00:00');
        $alreadySet = $this->createEntity($source, ['next_scrape_at' => $scheduled]);
        $needsSchedule = $this->createEntity($source);

        (new SetInitialScrapingTimeJob)->handle();

        $alreadySet->refresh();
        $needsSchedule->refresh();
        $this->assertTrue($alreadySet->next_scrape_at->equalTo($scheduled));
        $this->assertNotNull($needsSchedule->next_scrape_at);
        $this->assertTrue($needsSchedule->next_scrape_at->equalTo(now()));

        Carbon::setTestNow();
    }

    public function test_processes_multiple_entities_in_one_run(): void
    {
        Carbon::setTestNow('2026-04-01 10:00:00');
        $source = Source::factory()->create();
        $a = $this->createEntity($source, ['url' => 'https://example.com/a', 'url_hash' => sha1('https://example.com/a')]);
        $b = $this->createEntity($source, ['url' => 'https://example.com/b', 'url_hash' => sha1('https://example.com/b')]);
        $c = $this->createEntity($source, ['url' => 'https://example.com/c', 'url_hash' => sha1('https://example.com/c')]);

        (new SetInitialScrapingTimeJob)->handle();

        foreach ([$a, $b, $c] as $entity) {
            $entity->refresh();
            $this->assertNotNull($entity->next_scrape_at, 'Entity '.$entity->id.' should be scheduled');
            $this->assertTrue($entity->next_scrape_at->equalTo(now()));
        }

        Carbon::setTestNow();
    }

    public function test_sets_next_scrape_at_for_non_failed_statuses_other_than_pending(): void
    {
        Carbon::setTestNow('2026-04-01 10:00:00');
        $source = Source::factory()->create();
        $entity = $this->createEntity($source, [
            'scraping_status' => ScrapingStatus::QUEUED,
        ]);

        (new SetInitialScrapingTimeJob)->handle();

        $entity->refresh();
        $this->assertNotNull($entity->next_scrape_at);
        $this->assertTrue($entity->next_scrape_at->equalTo(now()));

        Carbon::setTestNow();
    }
}
