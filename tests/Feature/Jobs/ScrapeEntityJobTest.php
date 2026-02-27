<?php

namespace Tests\Feature\Jobs;

use App\Contracts\PageClassifier\ClassificationResult;
use App\Contracts\PageParser\PageData;
use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Contracts\VerticalResolver\Vertical as ContractVertical;
use App\Contracts\VerticalResolver\VerticalMatch;
use App\Enums\ContentType;
use App\Enums\PageType;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use App\Jobs\ScrapeEntityJob;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Source;
use App\Models\Vertical as VerticalModel;
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

    public function test_success_when_no_verticals_in_database_does_not_call_vertical_resolver(): void
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
        $pageData->setMarkdownContent("# Hello\nContent");
        $pageData->setExcerpt('Excerpt');
        $pageData->setLinkedPageUrls([]);
        $pageData->setPublishedAt(Carbon::now());
        $pageData->setUpdatedAt(Carbon::now());
        $pageData->setCanonicalNumber(0);
        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addHours(6));

        \App\Facades\PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        \App\Facades\PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        \App\Facades\ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);
        // When there are no verticals, we still call propose() (to allow suggestions),
        // but resolve() is skipped because there is nothing to match.
        \App\Facades\VerticalResolver::shouldReceive('propose')->once()->andReturn([]);
        \App\Facades\VerticalResolver::shouldReceive('resolve')->never();

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(200, [], '<html><body>Hello</body></html>');
            }
        };
        $job->handle();

        $this->assertDatabaseCount('verticals', 0);
        $entity->refresh();
        $this->assertSame(ScrapingStatus::SUCCESS->value, $entity->scraping_status->value);
        $this->assertCount(0, $entity->verticals);
    }

    public function test_success_syncs_resolved_verticals_to_entity(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'snapshots_count' => 0,
        ]);

        $verticalNews = VerticalModel::create(['name' => 'News', 'description' => 'News articles']);
        $verticalDocs = VerticalModel::create(['name' => 'Docs', 'description' => 'Documentation']);

        $classification = ClassificationResult::fromArray([
            'page_type' => PageType::DETAIL->value,
            'content_type' => ContentType::ARTICLE->value,
            'temporal' => Temporal::BREAKING->value,
            'description' => 'Desc',
        ]);
        $pageData = new PageData;
        $pageData->setMarkdownContent("# News\nArticle content");
        $pageData->setExcerpt('Excerpt');
        $pageData->setLinkedPageUrls([]);
        $pageData->setPublishedAt(Carbon::now());
        $pageData->setUpdatedAt(Carbon::now());
        $pageData->setCanonicalNumber(0);
        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addHours(6));

        $matches = [
            new VerticalMatch((string) $verticalNews->id, 0.9),
            new VerticalMatch((string) $verticalDocs->id, 0.5),
        ];

        \App\Facades\PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        \App\Facades\PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        \App\Facades\ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);
        \App\Facades\VerticalResolver::shouldReceive('resolve')->once()->andReturn($matches);
        \App\Facades\VerticalResolver::shouldReceive('propose')->once()->andReturn([]);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(200, [], '<html><body>News</body></html>');
            }
        };
        $job->handle();

        $entity->refresh();
        $this->assertSame(ScrapingStatus::SUCCESS->value, $entity->scraping_status->value);
        $entity->load('verticals');
        $this->assertCount(2, $entity->verticals);
        $verticalIds = $entity->verticals->pluck('id')->all();
        $this->assertContains($verticalNews->id, $verticalIds);
        $this->assertContains($verticalDocs->id, $verticalIds);
    }

    public function test_success_syncs_resolved_verticals_and_their_ancestors_to_entity(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'snapshots_count' => 0,
        ]);

        $parent = VerticalModel::create(['name' => 'Tech', 'description' => 'Technology']);
        $child = VerticalModel::create(['name' => 'AI', 'description' => 'Artificial intelligence', 'parent_id' => $parent->id]);

        $classification = ClassificationResult::fromArray([
            'page_type' => PageType::DETAIL->value,
            'content_type' => ContentType::ARTICLE->value,
            'temporal' => Temporal::BREAKING->value,
            'description' => 'Desc',
        ]);
        $pageData = new PageData;
        $pageData->setMarkdownContent("AI content");
        $pageData->setExcerpt('Excerpt');
        $pageData->setLinkedPageUrls([]);
        $pageData->setPublishedAt(Carbon::now());
        $pageData->setUpdatedAt(Carbon::now());
        $pageData->setCanonicalNumber(0);
        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addHours(6));

        $matches = [
            new VerticalMatch((string) $child->id, 0.9),
        ];

        \App\Facades\PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        \App\Facades\PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        \App\Facades\ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);
        \App\Facades\VerticalResolver::shouldReceive('resolve')->once()->andReturn($matches);
        \App\Facades\VerticalResolver::shouldReceive('propose')->once()->andReturn([]);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(200, [], '<html><body>AI</body></html>');
            }
        };
        $job->handle();

        $entity->refresh();
        $entity->load('verticals');
        $verticalIds = $entity->verticals->pluck('id')->all();
        $this->assertContains($child->id, $verticalIds);
        $this->assertContains($parent->id, $verticalIds);
    }

    public function test_success_creates_and_attaches_proposed_verticals(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'snapshots_count' => 0,
        ]);

        $existingVertical = VerticalModel::create(['name' => 'News', 'description' => 'News']);

        $classification = ClassificationResult::fromArray([
            'page_type' => PageType::DETAIL->value,
            'content_type' => ContentType::ARTICLE->value,
            'temporal' => Temporal::EVERGREEN->value,
            'description' => 'Desc',
        ]);
        $pageData = new PageData;
        $pageData->setMarkdownContent("Tech and product docs.");
        $pageData->setExcerpt('Excerpt');
        $pageData->setLinkedPageUrls([]);
        $pageData->setPublishedAt(Carbon::now());
        $pageData->setUpdatedAt(Carbon::now());
        $pageData->setCanonicalNumber(0);
        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addHours(6));

        $proposals = [
            new ContractVertical('Tech', 'Technology and product content'),
        ];

        \App\Facades\PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        \App\Facades\PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        \App\Facades\ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);
        \App\Facades\VerticalResolver::shouldReceive('resolve')->once()->andReturn([]);
        \App\Facades\VerticalResolver::shouldReceive('propose')->once()->andReturn($proposals);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(200, [], '<html><body>Tech</body></html>');
            }
        };
        $job->handle();

        $this->assertDatabaseHas('verticals', ['name' => 'Tech', 'description' => 'Technology and product content']);
        $techVertical = VerticalModel::where('name', 'Tech')->first();
        $this->assertNotNull($techVertical);

        $entity->refresh();
        $entity->load('verticals');
        $this->assertSame(ScrapingStatus::SUCCESS->value, $entity->scraping_status->value);
        // Proposed verticals are created and attached to the source, but attaching to the entity
        // is decided solely by resolve() (which we mocked to return an empty array here).
        $this->assertCount(0, $entity->verticals);

        $techVertical->load('sources');
        $this->assertTrue($techVertical->sources->contains('id', $source->id));
    }

    public function test_vertical_resolver_receives_markdown_content(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
            'snapshots_count' => 0,
        ]);

        VerticalModel::create(['name' => 'News', 'description' => 'News']);

        $markdownContent = "# Headline\n\nParagraph with **bold** and [link](https://example.com).";
        $classification = ClassificationResult::fromArray([
            'page_type' => PageType::DETAIL->value,
            'content_type' => ContentType::ARTICLE->value,
            'temporal' => Temporal::BREAKING->value,
            'description' => 'Desc',
        ]);
        $pageData = new PageData;
        $pageData->setMarkdownContent($markdownContent);
        $pageData->setExcerpt('Excerpt');
        $pageData->setLinkedPageUrls([]);
        $pageData->setPublishedAt(Carbon::now());
        $pageData->setUpdatedAt(Carbon::now());
        $pageData->setCanonicalNumber(0);
        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addHours(6));

        \App\Facades\PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        \App\Facades\PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        \App\Facades\ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);
        \App\Facades\VerticalResolver::shouldReceive('resolve')
            ->once()
            ->with(Mockery::on(function ($content) use ($markdownContent) {
                return $content === $markdownContent;
            }), Mockery::type('array'))
            ->andReturn([]);
        \App\Facades\VerticalResolver::shouldReceive('propose')
            ->once()
            ->with(Mockery::on(function ($content) use ($markdownContent) {
                return $content === $markdownContent;
            }), Mockery::type('array'))
            ->andReturn([]);

        $job = new class($entity) extends ScrapeEntityJob {
            protected function fetchUrl(string $url): ResponseInterface
            {
                return new Response(200, [], '<html><body>Different raw HTML</body></html>');
            }
        };
        $job->handle();

        $entity->refresh();
        $this->assertSame(ScrapingStatus::SUCCESS->value, $entity->scraping_status->value);
    }
}
