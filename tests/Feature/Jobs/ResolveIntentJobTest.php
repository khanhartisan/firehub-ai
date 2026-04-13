<?php

namespace Tests\Feature\Jobs;

use App\Contracts\IntentResolver\Intent as IntentDto;
use App\Contracts\IntentResolver\IntentableIntent;
use App\Contracts\IntentResolver\IntentableIntents;
use App\Contracts\VectorDB\Vector;
use App\Enums\ContentType;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\PageType;
use App\Enums\ScrapableType;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use App\Facades\IntentResolver;
use App\Facades\TextEmbedding;
use App\Facades\VectorDB;
use App\Jobs\ResolveIntentJob;
use App\Models\Intent;
use App\Models\IntentPage;
use App\Models\Page;
use App\Models\Source;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Embeddings;
use Mockery;
use Tests\TestCase;

class ResolveIntentJobTest extends TestCase
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

    /**
     * Stub only {@see CacheManager::lock()}; other calls (e.g. driver()) use the real manager.
     * Do not use {@see Cache::shouldReceive('lock')} alone — it replaces the whole manager and breaks driver().
     */
    private function fakeResolveIntentLockAlwaysAcquires(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->andReturnTrue();
        $lock->shouldReceive('release');

        $partial = Mockery::mock($this->cacheManagerBackup)->makePartial();
        $partial->shouldReceive('lock')
            ->with(
                Mockery::on(fn (string $name): bool => str_contains($name, 'ResolveIntentJob')),
                Mockery::type('int')
            )
            ->andReturn($lock);

        $this->app->instance('cache', $partial);
        Cache::swap($partial);
    }

    public function test_returns_immediately_when_lock_cannot_be_acquired(): void
    {
        Bus::fake();

        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturnFalse();

        $job = new ResolveIntentJob();

        Cache::shouldReceive('lock')
            ->once()
            ->with(ResolveIntentJob::class, $job->uniqueFor)
            ->andReturn($lock);

        IntentResolver::shouldReceive('resolve')->never();

        $job->handle();

        Bus::assertNotDispatched(ResolveIntentJob::class);
    }

    public function test_returns_when_an_intent_is_embeddable_but_not_yet_embedded(): void
    {
        Bus::fake();

        $this->fakeResolveIntentLockAlwaysAcquires();

        $intent = new Intent;
        $intent->language = Language::EN;
        $intent->title = 'Pending intent';
        $intent->description = 'Description long enough for embeddable sync.';
        $intent->save();

        IntentResolver::shouldReceive('resolve')->never();

        $this->makeShortTimeoutJob()->handle();

        Bus::assertNotDispatched(ResolveIntentJob::class);
    }

    public function test_resolves_embedded_page_creates_intent_pivot_and_sets_intent_resolved_at(): void
    {
        $this->fakeResolveIntentLockAlwaysAcquires();

        Bus::fake();

        $source = Source::create(['base_url' => 'https://example.com']);
        $page = Page::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page-intent',
            'url_hash' => sha1('https://example.com/page-intent'),
            'type' => ScrapableType::TEXT,
            'page_type' => PageType::DETAIL,
            'scraping_status' => ScrapingStatus::SUCCESS,
            'title' => 'Page title',
            'description' => 'Page description for embedding text.',
            'content_type' => ContentType::ARTICLE,
            'temporal' => Temporal::EVERGREEN,
            'language' => Language::EN,
        ]);

        $embedding = new Vector(Embeddings::fakeEmbedding(1536));
        $page->setEmbedding($embedding);
        $page->intent_resolved_at = null;
        $page->save();

        $intentDto = (new IntentDto)
            ->setTitle('Resolved intent title')
            ->setDescription(str_repeat('Resolved intent description. ', 12))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $row = (new IntentableIntent)
            ->setIntent($intentDto)
            ->setRelevance(0.88);

        $resolved = (new IntentableIntents)->setIntentableIntents([$row]);

        IntentResolver::shouldReceive('resolve')
            ->once()
            ->andReturn($resolved);

        IntentResolver::shouldReceive('mergeIntents')->never();

        $intentVector = new Vector(Embeddings::fakeEmbedding(1536));
        TextEmbedding::shouldReceive('embed')
            ->once()
            ->andReturn($intentVector);

        VectorDB::shouldReceive('search')->once()->andReturn([]);

        $this->makeShortTimeoutJob()->handle();

        $page->refresh();
        $this->assertNotNull($page->intent_resolved_at);

        $this->assertSame(1, Intent::query()->count());
        $this->assertSame(1, IntentPage::query()->where('page_id', $page->id)->count());

        $pivot = IntentPage::query()->where('page_id', $page->id)->first();
        $this->assertEqualsWithDelta(0.88, $pivot->relevance, 0.0001);

        Bus::assertDispatched(ResolveIntentJob::class);
    }

    public function test_does_not_dispatch_follow_up_when_no_work_was_done(): void
    {
        $this->fakeResolveIntentLockAlwaysAcquires();

        Bus::fake();

        IntentResolver::shouldReceive('resolve')->never();

        $this->makeShortTimeoutJob()->handle();

        Bus::assertNotDispatched(ResolveIntentJob::class);
    }

    /**
     * Parent job loops until timeout − 5s; keep timeout tiny so tests finish quickly.
     */
    private function makeShortTimeoutJob(): ResolveIntentJob
    {
        return new class extends ResolveIntentJob
        {
            public int $timeout = 6;
        };
    }
}
