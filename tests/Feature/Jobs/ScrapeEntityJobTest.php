<?php

namespace Tests\Feature\Jobs;

use App\Contracts\PageClassifier\ClassificationResult;
use App\Contracts\PageParser\PageData;
use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Enums\ContentType;
use App\Enums\PageType;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use App\Jobs\ScrapeEntityJob;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Source;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class ScrapeEntityJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('queue.max_scrape_attempts', 5);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_skips_entity_when_status_is_not_queued(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::PENDING,
        ]);

        $job = new ScrapeEntityJob($entity);
        $job->handle();

        $this->assertDatabaseCount('snapshots', 0);
        $entity->refresh();
        $this->assertSame(ScrapingStatus::PENDING->value, $entity->scraping_status->value);
    }

    public function test_creates_snapshot_with_failed_status_on_http_4xx(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'attempts' => 0,
            'snapshots_count' => 0,
        ]);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(404, [], 'Not Found');
            }
        };
        $job->handle();

        $this->assertDatabaseCount('snapshots', 1);
        $snapshot = Snapshot::where('entity_id', $entity->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame($entity->id, $snapshot->entity_id);
        $this->assertSame(ScrapingStatus::FAILED->value, $snapshot->scraping_status->value);
        $this->assertSame(1, $snapshot->version);
        $this->assertNotNull($snapshot->fetch_duration_ms);

        $entity->refresh();
        $this->assertSame(1, $entity->attempts);
        $this->assertSame(ScrapingStatus::FAILED->value, $entity->scraping_status->value);
        $this->assertNotNull($entity->next_scrape_at);
        $this->assertSame(1, $entity->snapshots_count);
    }

    public function test_creates_snapshot_with_blocked_status_on_403(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'attempts' => 0,
            'snapshots_count' => 0,
        ]);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(403, [], 'Forbidden');
            }
        };
        $job->handle();

        $snapshot = Snapshot::where('entity_id', $entity->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(ScrapingStatus::BLOCKED->value, $snapshot->scraping_status->value);
        $entity->refresh();
        $this->assertSame(ScrapingStatus::BLOCKED->value, $entity->scraping_status->value);
    }

    public function test_creates_snapshot_with_timeout_status_on_connect_exception(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'attempts' => 0,
            'snapshots_count' => 0,
        ]);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                throw new ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', $url));
            }
        };
        $job->handle();

        $this->assertDatabaseCount('snapshots', 1);
        $snapshot = Snapshot::where('entity_id', $entity->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame($entity->id, $snapshot->entity_id);
        $this->assertSame(ScrapingStatus::TIMEOUT->value, $snapshot->scraping_status->value);
        $this->assertSame(1, $snapshot->version);
        $this->assertNotNull($snapshot->fetch_duration_ms);

        $entity->refresh();
        $this->assertSame(1, $entity->attempts);
        $this->assertSame(ScrapingStatus::TIMEOUT->value, $entity->scraping_status->value);
        $this->assertSame(1, $entity->snapshots_count);
    }

    public function test_stops_retrying_after_max_attempts_and_creates_snapshot(): void
    {
        Config::set('queue.max_scrape_attempts', 2);
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'attempts' => 1,
            'snapshots_count' => 1,
        ]);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(500, [], 'Error');
            }
        };
        $job->handle();

        $entity->refresh();
        $this->assertSame(2, $entity->attempts);
        $this->assertNull($entity->next_scrape_at);
        $this->assertSame(ScrapingStatus::FAILED->value, $entity->scraping_status->value);
        $this->assertSame(2, $entity->snapshots_count);
        $this->assertDatabaseCount('snapshots', 1);
        $latestSnapshot = Snapshot::where('entity_id', $entity->id)->orderByDesc('version')->first();
        $this->assertNotNull($latestSnapshot);
        $this->assertSame(2, $latestSnapshot->version);
        $this->assertSame(ScrapingStatus::FAILED->value, $latestSnapshot->scraping_status->value);
    }

    public function test_success_creates_snapshot_with_correct_data_and_updates_entity(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'attempts' => 1,
            'snapshots_count' => 0,
        ]);

        $classification = ClassificationResult::fromArray([
            'page_type' => PageType::DETAIL->value,
            'content_type' => ContentType::ARTICLE->value,
            'temporal' => Temporal::BREAKING->value,
            'description' => 'Test description',
        ]);

        $pageData = new PageData;
        $pageData->setMarkdownContent("# Hello\n[link](https://example.com/other)");
        $pageData->setExcerpt('Excerpt');
        $pageData->setLinkedPageUrls([]);
        $pageData->setPublishedAt(Carbon::now());
        $pageData->setUpdatedAt(Carbon::now());
        $pageData->setCanonicalNumber(0);

        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addHours(6));

        \App\Facades\PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        \App\Facades\PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        \App\Facades\ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(200, [], '<html><body>Hello</body></html>');
            }
        };
        $job->handle();

        $this->assertDatabaseCount('snapshots', 1);
        $snapshot = Snapshot::where('entity_id', $entity->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame($entity->id, $snapshot->entity_id);
        $this->assertSame(ScrapingStatus::SUCCESS, $snapshot->scraping_status);
        $this->assertSame(1, $snapshot->version);
        $this->assertNotNull($snapshot->content_length);
        $this->assertSame(1, $snapshot->link_count);
        $this->assertSame(0, $snapshot->media_count);
        $this->assertSame(0, $snapshot->structured_data_count);
        $this->assertNotNull($snapshot->fetch_duration_ms);

        $entity->refresh();
        $this->assertSame(ScrapingStatus::SUCCESS->value, $entity->scraping_status->value);
        $this->assertSame(0, $entity->attempts);
        $this->assertSame(1, $entity->snapshots_count);
        $this->assertNotNull($entity->next_scrape_at);
    }

    public function test_creates_linked_entities_with_correct_data_same_host_only(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'snapshots_count' => 0,
        ]);

        $classification = ClassificationResult::fromArray([
            'page_type' => PageType::DETAIL->value,
            'content_type' => ContentType::ARTICLE->value,
            'temporal' => Temporal::BREAKING->value,
            'description' => 'Desc',
        ]);

        $pageData = new PageData;
        $pageData->setMarkdownContent('');
        $pageData->setExcerpt('');
        $pageData->setLinkedPageUrls([
            'https://example.com/new-page',
            'https://other.com/external',
        ]);
        $pageData->setPublishedAt(null);
        $pageData->setUpdatedAt(null);
        $pageData->setCanonicalNumber(0);

        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addDay());

        \App\Facades\PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        \App\Facades\PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        \App\Facades\ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(200, [], '<html></html>');
            }
        };
        $job->handle();

        $this->assertDatabaseCount('entities', 2);
        $newEntity = Entity::where('source_id', $source->id)->where('url', 'https://example.com/new-page')->first();
        $this->assertNotNull($newEntity);
        $this->assertSame($source->id, $newEntity->source_id);
        $this->assertSame('https://example.com/new-page', $newEntity->url);
        $this->assertSame(sha1('https://example.com/new-page'), $newEntity->url_hash);
        $this->assertSame(ScrapingStatus::PENDING->value, $newEntity->scraping_status->value);

        $this->assertDatabaseMissing('entities', [
            'source_id' => $source->id,
            'url' => 'https://other.com/external',
        ]);
    }

    public function test_does_not_duplicate_linked_entity_when_url_already_exists_for_source(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'snapshots_count' => 0,
        ]);

        Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/existing',
            'url_hash' => sha1('https://example.com/existing'),
        ]);

        $classification = ClassificationResult::fromArray([
            'page_type' => PageType::DETAIL->value,
            'content_type' => ContentType::ARTICLE->value,
            'temporal' => Temporal::BREAKING->value,
            'description' => 'Desc',
        ]);

        $pageData = new PageData;
        $pageData->setMarkdownContent('');
        $pageData->setExcerpt('');
        $pageData->setLinkedPageUrls(['https://example.com/existing', 'https://example.com/new-one']);
        $pageData->setPublishedAt(null);
        $pageData->setUpdatedAt(null);
        $pageData->setCanonicalNumber(0);

        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addDay());

        \App\Facades\PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        \App\Facades\PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        \App\Facades\ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(200, [], '<html></html>');
            }
        };
        $job->handle();

        $this->assertDatabaseCount('entities', 3);
        $existingCount = Entity::where('source_id', $source->id)->where('url', 'https://example.com/existing')->count();
        $this->assertSame(1, $existingCount);
        $newOne = Entity::where('source_id', $source->id)->where('url', 'https://example.com/new-one')->first();
        $this->assertNotNull($newOne);
        $this->assertSame(sha1('https://example.com/new-one'), $newOne->url_hash);
    }
}
