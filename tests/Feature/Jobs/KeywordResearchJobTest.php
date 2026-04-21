<?php

namespace Tests\Feature\Jobs;

use App\Contracts\Model\Keyword\SearchEngineData;
use App\Contracts\Model\Keyword\SearchEngineDriverData;
use App\Contracts\SearchEngine\SearchResult;
use App\Contracts\SearchEngine\SearchResults;
use App\Enums\KeywordStatus;
use App\Enums\ScrapingStatus;
use App\Jobs\KeywordResearchJob;
use App\Models\Keyword;
use App\Models\KeywordPage;
use App\Models\Page;
use App\Models\Source;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class KeywordResearchJobTest extends TestCase
{
    use RefreshDatabase;

    private CacheManager $cacheManagerBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheManagerBackup = $this->app->make('cache');
    }

    protected function tearDown(): void
    {
        $this->app->instance('cache', $this->cacheManagerBackup);
        Cache::swap($this->cacheManagerBackup);

        Mockery::close();
        parent::tearDown();
    }

    private function fakeKeywordResearchLockAlwaysAcquires(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->andReturnTrue();
        $lock->shouldReceive('release');

        $partial = Mockery::mock($this->cacheManagerBackup)->makePartial();
        $partial->shouldReceive('lock')
            ->with(
                Mockery::type('string'),
                Mockery::type('int')
            )
            ->andReturn($lock);

        $this->app->instance('cache', $partial);
        Cache::swap($partial);
    }

    public function test_it_saves_keyword_pages_with_driver_and_position_and_marks_keyword_researched(): void
    {
        Bus::fake();
        $this->fakeKeywordResearchLockAlwaysAcquires();

        config()->set('search_engine.drivers.google', ['provider' => 'searchapi']);
        config()->set('search_engine.drivers.bing', ['provider' => 'searchapi']);

        $keyword = Keyword::query()->create([
            'keyword' => 'ai tools',
            'hash' => sha1('ai tools'),
            'status' => KeywordStatus::PENDING,
            'search_engine_data' => $this->makeSearchEngineData([
                'google' => [
                    new SearchResult('A', 'https://example.com/a', null, 1),
                    new SearchResult('B', 'https://example.com/b', null, 2),
                ],
                'bing' => [
                    new SearchResult('A', 'https://example.com/a', null, 3),
                ],
            ]),
        ]);

        $source = Source::query()->create(['base_url' => 'https://example.com/']);
        Page::query()->create([
            'source_id' => $source->id,
            'url' => 'https://example.com/a',
            'scraping_status' => ScrapingStatus::SUCCESS,
        ]);
        Page::query()->create([
            'source_id' => $source->id,
            'url' => 'https://example.com/b',
            'scraping_status' => ScrapingStatus::SUCCESS,
        ]);

        $job = new KeywordResearchJob($keyword, ['google', 'bing']);
        $job->handle();

        $this->assertDatabaseHas('keyword_page', [
            'search_engine_driver' => 'google',
            'keyword_id' => $keyword->id,
            'position' => 1,
        ]);
        $this->assertDatabaseHas('keyword_page', [
            'search_engine_driver' => 'google',
            'keyword_id' => $keyword->id,
            'position' => 2,
        ]);
        $this->assertDatabaseHas('keyword_page', [
            'search_engine_driver' => 'bing',
            'keyword_id' => $keyword->id,
            'position' => 3,
        ]);
        $this->assertSame(3, KeywordPage::query()->where('keyword_id', $keyword->id)->count());

        $keyword->refresh();
        $this->assertSame(KeywordStatus::RESEARCHED, $keyword->status);
        $this->assertNotNull($keyword->researched_at);
    }

    public function test_it_creates_missing_page_persists_keyword_page_and_redispatches_when_page_is_not_final(): void
    {
        Bus::fake();
        $this->fakeKeywordResearchLockAlwaysAcquires();

        config()->set('search_engine.drivers.google', ['provider' => 'searchapi']);

        $keyword = Keyword::query()->create([
            'keyword' => 'ai agents',
            'hash' => sha1('ai agents'),
            'status' => KeywordStatus::PENDING,
            'search_engine_data' => $this->makeSearchEngineData([
                'google' => [
                    new SearchResult('New', 'https://newsite.com/path', null, 1),
                ],
            ]),
        ]);

        $job = new KeywordResearchJob($keyword, ['google']);
        $job->handle();

        $page = Page::query()->where('url_hash', sha1('https://newsite.com/path'))->first();
        $this->assertNotNull($page);
        $this->assertTrue($page->ignore_scraping_budget);

        $this->assertDatabaseHas('keyword_page', [
            'search_engine_driver' => 'google',
            'keyword_id' => $keyword->id,
            'page_id' => $page->id,
            'position' => 1,
        ]);

        Bus::assertDispatched(KeywordResearchJob::class);
    }

    /**
     * @param array<string, list<SearchResult>> $driverResults
     */
    private function makeSearchEngineData(array $driverResults): SearchEngineData
    {
        $data = new SearchEngineData;

        foreach ($driverResults as $driver => $results) {
            $searchResults = new SearchResults($results);
            $searchResults->setUpdatedAt(now());

            $driverData = new SearchEngineDriverData;
            $driverData->setSearchResults($searchResults);

            $data->setDriverData($driver, $driverData);
        }

        return $data;
    }
}
