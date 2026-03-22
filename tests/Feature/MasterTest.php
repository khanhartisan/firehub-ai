<?php

namespace Tests\Feature;

use App\Contracts\PageClassifier\ClassificationResult;
use App\Contracts\PageParser\PageData;
use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Contracts\VectorDB\Vector;
use App\Enums\ContentType;
use App\Enums\PageType;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use App\Facades\PageClassifier;
use App\Facades\PageParser;
use App\Facades\ScrapePolicyEngine;
use App\Facades\Scraper;
use App\Facades\TextEmbedding;
use App\Facades\VerticalResolver;
use App\Jobs\EmbeddingJob;
use App\Jobs\ScheduleEmbeddingJob;
use App\Jobs\ScrapeSourcesJob;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Source;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Mockery;
use Tests\TestCase;

/**
 * End-to-end exercise of the main product pipeline (with external I/O mocked):
 *
 * 1. Scheduler-style source intake: {@see ScrapeSourcesJob} ensures a home-page entity and
 *    dispatches {@see \App\Jobs\ScrapeEntityJob} (runs on the sync queue in tests).
 * 2. Scrape stages: HTTP fetch via {@see Scraper}, HTML stored on the default disk, snapshot +
 *    entity updated using classifier, parser, policy, and vertical resolver facades.
 * 3. Embedding scheduler: {@see ScheduleEmbeddingJob} discovers morph-mapped embeddable models
 *    and queues {@see EmbeddingJob}; we execute those jobs to persist vectors using a mocked
 *    {@see TextEmbedding} driver.
 */
class MasterTest extends TestCase
{
    use RefreshDatabase;

    private const string BASE_URL = 'https://master-flow.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.scrape_sources_chunk_size', 10);
        Config::set('queue.scrape_sources_max_seconds', 300);
        Config::set('queue.max_scrape_attempts', 5);
        Config::set('queue.max_scraping_queue_size', 1000);
        Config::set('queue.max_default_queue_size', 1000);

        Storage::fake(config('filesystems.default'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_full_pipeline_from_source_intake_through_scrape_to_embedding(): void
    {
        $classification = ClassificationResult::fromArray([
            'page_type' => PageType::DETAIL->value,
            'content_type' => ContentType::ARTICLE->value,
            'temporal' => Temporal::BREAKING->value,
            'description' => 'Integrated master-flow article body.',
        ]);

        $pageData = new PageData;
        $pageData->setMarkdownContent("# Master flow\n[internal](https://master-flow.example.test/docs)");
        $pageData->setExcerpt('Master flow excerpt');
        $pageData->setLinkedPageUrls([]);
        $pageData->setPublishedAt(Carbon::now());
        $pageData->setUpdatedAt(Carbon::now());
        $pageData->setCanonicalNumber(0);

        $policyResult = (new PolicyResult)->setNextScrapeAt(Carbon::now()->addHours(6));

        Scraper::shouldReceive('fetch')
            ->once()
            ->withArgs(function (string $url, mixed $options = null): bool {
                return $url === self::BASE_URL;
            })
            ->andReturn(new Response(200, ['Content-Type' => 'text/html'], '<html><body>Master flow</body></html>'));

        PageClassifier::shouldReceive('classify')->once()->andReturn($classification);
        PageParser::shouldReceive('parse')->once()->andReturn($pageData);
        ScrapePolicyEngine::shouldReceive('evaluate')->once()->andReturn($policyResult);
        VerticalResolver::shouldReceive('propose')->once()->andReturn([]);
        VerticalResolver::shouldReceive('resolve')->never();

        $source = Source::factory()->create([
            'base_url' => self::BASE_URL,
            'description' => 'Catalog blurb for embedding.',
        ]);

        (new ScrapeSourcesJob)->handle();

        $entity = Entity::query()
            ->where('source_id', $source->id)
            ->where('url', self::BASE_URL)
            ->first();

        $this->assertNotNull($entity);
        $entity->refresh();
        $this->assertSame(ScrapingStatus::SUCCESS->value, $entity->scraping_status->value);
        $this->assertSame(0, $entity->attempts);
        $this->assertTrue($entity->isEmbeddable());
        $this->assertFalse($entity->isEmbedded());

        $this->assertDatabaseCount('snapshots', 1);
        $snapshot = Snapshot::where('entity_id', $entity->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(ScrapingStatus::SUCCESS, $snapshot->scraping_status);
        Storage::assertExists($snapshot->file_path);

        $source->refresh();
        $this->assertTrue($source->isEmbeddable());
        $this->assertFalse($source->isEmbedded());

        $vector = new Vector(Embeddings::fakeEmbedding(1536));

        Queue::fake();

        TextEmbedding::shouldReceive('embed')
            ->twice()
            ->andReturn($vector);

        (new ScheduleEmbeddingJob(perModelLimit: 10))->handle();

        Queue::assertPushed(EmbeddingJob::class, 2);

        foreach (Queue::pushed(EmbeddingJob::class) as $job) {
            $this->assertInstanceOf(EmbeddingJob::class, $job);
            $job->handle();
        }

        $entity->refresh();
        $source->refresh();
        $this->assertTrue($entity->isEmbedded());
        $this->assertTrue($source->isEmbedded());
        $this->assertNotNull($entity->getVector());
        $this->assertNotNull($source->getVector());
    }
}
