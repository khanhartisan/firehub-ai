<?php

namespace Tests\Feature\Jobs;

use App\Contracts\PageParser\PageData;
use App\Enums\ScrapingStatus;
use App\Jobs\EmbeddingJob;
use App\Jobs\ScrapeFileJob;
use App\Jobs\ScrapePageJob;
use App\Models\File;
use App\Models\Fileable;
use App\Models\Page;
use App\Models\Snapshot;
use App\Models\Source;
use App\Utils\UrlNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileEnrichmentStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('queue.max_scrape_attempts', 5);
    }

    /**
     * @return object{run: callable(Page): ?bool}
     */
    private function makeRunner(Page $page): object
    {
        return new class($page) extends ScrapePageJob {
            public function __construct(Page $page)
            {
                parent::__construct($page);
            }

            public function run(Page $page): ?bool
            {
                return $this->handleFileEnrichmentStage($page);
            }
        };
    }

    private function storeSnapshotPageData(Snapshot $snapshot, PageData $pageData): void
    {
        Storage::put($snapshot->getFilePathForPageData(), $pageData->toJson());
    }

    /**
     * @return array{0: Page, 1: Snapshot}
     */
    private function makePageWithSnapshot(): array
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $page = Page::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::PROCESSING,
        ]);
        $snapshot = Snapshot::create([
            'page_id' => $page->id,
            'version' => 1,
            'scraping_status' => ScrapingStatus::SUCCESS,
            'file_extension' => 'html',
        ]);
        $page->refresh();
        $page->load('currentSnapshot');

        return [$page, $snapshot];
    }

    public function test_returns_false_when_page_has_no_current_snapshot(): void
    {
        $source = Source::create(['base_url' => 'https://example.com']);
        $page = Page::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/alone',
            'url_hash' => sha1('https://example.com/alone'),
            'scraping_status' => ScrapingStatus::PROCESSING,
        ]);
        $page->refresh();

        $pageData = new PageData;
        $pageData->setMarkdownContent('# Hi');

        $runner = $this->makeRunner($page);
        $this->assertFalse($runner->run($page));
    }

    public function test_returns_true_when_markdown_has_no_file_urls_and_no_blocking_files(): void
    {
        [$page, $snapshot] = $this->makePageWithSnapshot();

        $pageData = new PageData;
        $pageData->setMarkdownContent("# Title\n\nPlain text without links to files.");
        $this->storeSnapshotPageData($snapshot, $pageData);

        $runner = $this->makeRunner($page);
        $this->assertTrue($runner->run($page));
    }

    public function test_creates_file_fileable_and_dispatches_scrape_when_extracted_file_is_pending(): void
    {
        Queue::fake();

        [$page, $snapshot] = $this->makePageWithSnapshot();

        $rawUrl = 'https://cdn.example.com/docs/report.pdf';
        $normalized = UrlNormalizer::normalize($rawUrl);
        $hash = sha1($normalized);

        $pageData = new PageData;
        $pageData->setMarkdownContent('Download [report]('.$rawUrl.')');
        $this->storeSnapshotPageData($snapshot, $pageData);

        $runner = $this->makeRunner($page);
        $this->assertNull($runner->run($page));

        $this->assertDatabaseHas('files', [
            'url_hash' => $hash,
        ]);

        $file = File::query()->where('url_hash', $hash)->first();
        $this->assertNotNull($file);

        $this->assertDatabaseHas('fileables', [
            'fileable_type' => $snapshot->getMorphClass(),
            'fileable_id' => $snapshot->getKey(),
            'file_id' => $file->id,
        ]);

        Queue::assertPushed(ScrapeFileJob::class, function (ScrapeFileJob $job) use ($file) {
            return $job->uniqueId() === $file->getKey();
        });
        Queue::assertNotPushed(EmbeddingJob::class);
    }

    public function test_returns_true_when_linked_files_are_scraped_and_not_awaiting_embedding(): void
    {
        Queue::fake();

        [$page, $snapshot] = $this->makePageWithSnapshot();

        $url = 'https://static.example.com/done.pdf';
        $normalized = UrlNormalizer::normalize($url);
        $hash = sha1($normalized);

        $file = File::query()->create([
            'url' => $normalized,
            'url_hash' => $hash,
            'scraping_status' => ScrapingStatus::SUCCESS,
        ]);

        Fileable::query()->create([
            'fileable_type' => $snapshot->getMorphClass(),
            'fileable_id' => $snapshot->getKey(),
            'file_id' => $file->id,
        ]);

        $pageData = new PageData;
        $pageData->setMarkdownContent(''); // no new URLs
        $this->storeSnapshotPageData($snapshot, $pageData);

        $runner = $this->makeRunner($page);
        $this->assertTrue($runner->run($page));

        Queue::assertNothingPushed();
    }

    public function test_dispatches_embedding_job_when_embeddable_file_not_yet_embedded(): void
    {
        Queue::fake();

        [$page, $snapshot] = $this->makePageWithSnapshot();

        $url = 'https://static.example.com/photo.jpg';
        $normalized = UrlNormalizer::normalize($url);
        $hash = sha1($normalized);

        $file = File::query()->create([
            'url' => $normalized,
            'url_hash' => $hash,
            'scraping_status' => ScrapingStatus::SUCCESS,
            'description' => 'caption for vision',
            'extension' => 'jpg',
        ]);

        Fileable::query()->create([
            'fileable_type' => $snapshot->getMorphClass(),
            'fileable_id' => $snapshot->getKey(),
            'file_id' => $file->id,
        ]);

        $pageData = new PageData;
        $pageData->setMarkdownContent('');
        $this->storeSnapshotPageData($snapshot, $pageData);

        $runner = $this->makeRunner($page);
        $this->assertNull($runner->run($page));

        Queue::assertPushed(EmbeddingJob::class, function (EmbeddingJob $job) use ($file) {
            return $job->uniqueId() === $file->getKey();
        });
    }

    public function test_does_not_create_duplicate_file_when_same_url_appears_twice_in_markdown(): void
    {
        Queue::fake();

        [$page] = $this->makePageWithSnapshot();

        $rawUrl = 'https://cdn.example.com/x.pdf';
        $normalized = UrlNormalizer::normalize($rawUrl);
        $hash = sha1($normalized);

        $pageData = new PageData;
        $pageData->setMarkdownContent(
            '[a]('.$rawUrl.') [b]('.$rawUrl.')'
        );
        $this->storeSnapshotPageData($page->currentSnapshot, $pageData);

        $runner = $this->makeRunner($page);
        $this->assertNull($runner->run($page));

        $this->assertSame(1, File::query()->where('url_hash', $hash)->count());
    }

    public function test_truncates_extracted_urls_to_first_100(): void
    {
        Queue::fake();

        [$page] = $this->makePageWithSnapshot();

        $lines = [];
        for ($i = 0; $i < 101; $i++) {
            $lines[] = 'https://limit.test/doc'.$i.'.pdf';
        }
        $pageData = new PageData;
        $pageData->setMarkdownContent(implode("\n", $lines));
        $this->storeSnapshotPageData($page->currentSnapshot, $pageData);

        $runner = $this->makeRunner($page);
        $this->assertNull($runner->run($page));

        $this->assertSame(100, File::query()->count());
    }

    public function test_failed_file_does_not_block_completion(): void
    {
        Queue::fake();

        [$page, $snapshot] = $this->makePageWithSnapshot();

        $file = File::query()->create([
            'url' => 'https://static.example.com/missing.pdf',
            'url_hash' => sha1(UrlNormalizer::normalize('https://static.example.com/missing.pdf')),
            'scraping_status' => ScrapingStatus::FAILED,
        ]);

        Fileable::query()->create([
            'fileable_type' => $snapshot->getMorphClass(),
            'fileable_id' => $snapshot->getKey(),
            'file_id' => $file->id,
        ]);

        $pageData = new PageData;
        $pageData->setMarkdownContent('');
        $this->storeSnapshotPageData($snapshot, $pageData);

        $runner = $this->makeRunner($page);
        $this->assertTrue($runner->run($page));

        Queue::assertNothingPushed();
    }
}
