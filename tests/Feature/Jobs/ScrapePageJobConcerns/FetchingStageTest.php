<?php

namespace Tests\Feature\Jobs\ScrapePageJobConcerns;

use App\Enums\ScrapingStatus;
use App\Jobs\ScrapePageJobConcerns\FetchingStage;
use App\Models\Page;
use App\Models\Snapshot;
use App\Models\Source;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class FetchingStageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return object{run: callable(Page): ?Snapshot, fetchCalled: bool}
     */
    private function makeRunner(): object
    {
        return new class {
            use FetchingStage;

            public bool $fetchCalled = false;

            public function run(Page $page): ?Snapshot
            {
                return $this->handleFetchingStage($page);
            }

            protected function fetchUrl(string $url): ResponseInterface
            {
                $this->fetchCalled = true;

                return new Response(200, ['Content-Type' => 'text/html'], '<html></html>');
            }
        };
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
            'scraping_status' => ScrapingStatus::QUEUED,
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

    public function test_reuses_current_snapshot_when_created_within_valid_time(): void
    {
        $snapshotCreatedAt = Carbon::parse('2024-06-01 10:00:00');
        Carbon::setTestNow($snapshotCreatedAt);

        [$page, $snapshot] = $this->makePageWithSnapshot();

        Carbon::setTestNow($snapshotCreatedAt->copy()->addMinutes(30));

        $runner = $this->makeRunner();
        $result = $runner->run($page);

        $this->assertFalse($runner->fetchCalled);
        $this->assertNotNull($result);
        $this->assertSame($snapshot->id, $result->id);
        $this->assertDatabaseCount('snapshots', 1);

        $page->refresh();
        $this->assertSame(ScrapingStatus::FETCHING, $page->scraping_status);
    }

    public function test_reuses_current_snapshot_at_exact_valid_time_boundary(): void
    {
        $snapshotCreatedAt = Carbon::parse('2024-06-01 10:00:00');
        Carbon::setTestNow($snapshotCreatedAt);

        [$page, $snapshot] = $this->makePageWithSnapshot();

        Carbon::setTestNow($snapshotCreatedAt->copy()->addSeconds(3600));

        $runner = $this->makeRunner();
        $result = $runner->run($page);

        $this->assertFalse($runner->fetchCalled);
        $this->assertSame($snapshot->id, $result->id);
        $this->assertDatabaseCount('snapshots', 1);
    }

    public function test_fetches_new_snapshot_when_current_snapshot_is_stale(): void
    {
        Storage::fake();

        $snapshotCreatedAt = Carbon::parse('2024-06-01 10:00:00');
        Carbon::setTestNow($snapshotCreatedAt);

        [$page, $snapshot] = $this->makePageWithSnapshot();

        Carbon::setTestNow($snapshotCreatedAt->copy()->addSeconds(3601));

        $runner = $this->makeRunner();
        $result = $runner->run($page);

        $this->assertTrue($runner->fetchCalled);
        $this->assertNotNull($result);
        $this->assertNotSame($snapshot->id, $result->id);
        $this->assertDatabaseCount('snapshots', 2);
        $this->assertSame(ScrapingStatus::SUCCESS, $result->scraping_status);
        $this->assertSame(2, $result->version);
    }

    public function test_fetches_when_page_has_no_current_snapshot(): void
    {
        Storage::fake();

        $source = Source::create(['base_url' => 'https://example.com']);
        $page = Page::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'scraping_status' => ScrapingStatus::QUEUED,
        ]);
        $page->refresh();

        $runner = $this->makeRunner();
        $result = $runner->run($page);

        $this->assertTrue($runner->fetchCalled);
        $this->assertNotNull($result);
        $this->assertDatabaseCount('snapshots', 1);
        $this->assertSame(ScrapingStatus::SUCCESS, $result->scraping_status);
        $this->assertSame(1, $result->version);
    }
}
