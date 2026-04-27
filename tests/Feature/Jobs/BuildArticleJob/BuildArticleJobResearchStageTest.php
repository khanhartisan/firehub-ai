<?php

namespace Tests\Feature\Jobs\BuildArticleJob;

use App\Contracts\CommonData\Keyword as KeywordData;
use App\Contracts\Model\Article\Context as ArticleContext;
use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\IntentType;
use App\Enums\KeywordStatus;
use App\Enums\Language;
use App\Enums\ScrapableType;
use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use App\Facades\IntentResolver;
use App\Facades\Synthesizer;
use App\Jobs\BuildArticleJob;
use App\Jobs\KeywordResearchJob;
use App\Models\Article;
use App\Models\Client;
use App\Models\Keyword;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BuildArticleJobResearchStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('synthesizer.default', 'basic');
        Synthesizer::clearResolvedInstance('synthesizer.manager');
        app()->forgetInstance('synthesizer.manager');
        app()->forgetInstance(\App\Contracts\Synthesizer\Synthesizer::class);
    }

    public function test_research_stage_bootstraps_keywords_and_checkpoints(): void
    {
        Bus::fake();

        Keyword::query()->create([
            'keyword' => 'ai copilots',
            'language' => Language::EN,
            'status' => KeywordStatus::RESEARCHING,
        ]);

        IntentResolver::shouldReceive('guessIntentKeywords')
            ->once()
            ->andReturn([
                $this->makeIntentKeywordRow('ai copilots'),
            ]);

        [$article, $job] = $this->makeArticleAndJobAtResearchStage();

        $result = $job->runResearchStage();
        $article->refresh();

        $this->assertNull($result);
        $this->assertCount(1, $article->stage_data->getResearchStageData()->getKeywords());
        $this->assertSame('ai copilots', $article->stage_data->getResearchStageData()->getKeywords()[0]->getKeyword());
        Bus::assertDispatched(KeywordResearchJob::class);
    }

    public function test_research_stage_waits_when_keyword_is_not_final(): void
    {
        Bus::fake();
        IntentResolver::shouldReceive('guessIntentKeywords')->never();

        [$article, $job] = $this->makeArticleAndJobAtResearchStage();
        $keyword = Keyword::query()->create([
            'keyword' => 'ai copilots',
            'language' => Language::EN,
            'status' => KeywordStatus::RESEARCHING,
        ]);

        $article->stage_data->getResearchStageData()
            ->setKeywords([
                $this->makeKeywordData('ai copilots'),
            ]);
        $article->save();
        $article->refresh();
        $job = new class($article->client, $article->id) extends BuildArticleJob
        {
            public function runResearchStage(): ?bool
            {
                return $this->handleResearchStage();
            }
        };

        $result = $job->runResearchStage();

        $this->assertNull($result);
        Bus::assertDispatched(KeywordResearchJob::class, function (KeywordResearchJob $dispatched) use ($keyword): bool {
            return $dispatched->uniqueId() === $keyword->id;
        });
    }

    public function test_research_stage_extracts_and_persists_points_grouped_by_url(): void
    {
        Bus::fake();
        IntentResolver::shouldReceive('guessIntentKeywords')->never();

        [$article, $job] = $this->makeArticleAndJobAtResearchStage();
        $keyword = Keyword::query()->create([
            'keyword' => 'ai copilots',
            'language' => Language::EN,
            'status' => KeywordStatus::RESEARCHED,
        ]);

        $page = Page::query()->create([
            'url' => 'https://example.com/ai-copilots',
            'title' => 'AI copilots adoption',
            'description' => 'Teams report measurable improvements.',
            'type' => ScrapableType::TEXT,
            'scraping_stage' => ScrapingStage::FINISHING,
            'scraping_status' => ScrapingStatus::SUCCESS,
            'ignore_scraping_budget' => true,
        ]);

        $keyword->pages()->attach($page->id, [
            'search_engine_driver' => 'test-driver',
            'position' => 1,
        ]);

        $article->stage_data->getResearchStageData()
            ->setKeywords([
                $this->makeKeywordData('ai copilots'),
            ]);
        $article->save();
        $article->refresh();
        $job = new class($article->client, $article->id) extends BuildArticleJob
        {
            public function runResearchStage(): ?bool
            {
                return $this->handleResearchStage();
            }
        };

        $result = $job->runResearchStage();
        $article->refresh();

        $this->assertNull($result);
        $pointsByUrl = $article->stage_data->getResearchStageData()->getPointsByPageUrl();
        $this->assertArrayHasKey('https://example.com/ai-copilots', $pointsByUrl);
        $this->assertNotEmpty($pointsByUrl['https://example.com/ai-copilots']);
    }

    public function test_research_stage_completes_within_bounded_runs_after_extraction_starts(): void
    {
        Bus::fake();
        IntentResolver::shouldReceive('guessIntentKeywords')->never();

        [$article, $job] = $this->makeArticleAndJobAtResearchStage();
        $keyword = Keyword::query()->create([
            'keyword' => 'ai copilots',
            'language' => Language::EN,
            'status' => KeywordStatus::RESEARCHED,
        ]);

        $page = Page::query()->create([
            'url' => 'https://example.com/ai-copilots',
            'title' => 'AI copilots adoption',
            'description' => 'Teams report measurable improvements.',
            'type' => ScrapableType::TEXT,
            'scraping_stage' => ScrapingStage::FINISHING,
            'scraping_status' => ScrapingStatus::SUCCESS,
            'ignore_scraping_budget' => true,
        ]);

        $keyword->pages()->attach($page->id, [
            'search_engine_driver' => 'test-driver',
            'position' => 1,
        ]);

        $article->stage_data->getResearchStageData()
            ->setKeywords([
                $this->makeKeywordData('ai copilots'),
            ]);
        $article->save();
        $article->refresh();
        $job = new class($article->client, $article->id) extends BuildArticleJob
        {
            public function runResearchStage(): ?bool
            {
                return $this->handleResearchStage();
            }
        };

        $result = null;
        for ($i = 0; $i < 12; $i++) {
            $result = $job->runResearchStage();
            $article->refresh();

            if ($result === true) {
                break;
            }
        }

        $this->assertTrue(
            $result === true,
            'Research stage should complete after a bounded number of runs once extraction has started.'
        );
    }

    /**
     * @return array{0: Article, 1: BuildArticleJob}
     */
    protected function makeArticleAndJobAtResearchStage(): array
    {
        $client = new Client;
        $client->name = 'client-'.str()->ulid();
        $client->context = (new ClientContext)->setDescription('Client context');
        $client->save();

        $article = new Article;
        $article->client()->associate($client);
        $article->context = (new ArticleContext)->setMeta(['raw_text' => 'Article context']);
        $article->status = ArticleStatus::UNREADY;
        $article->stage = ArticleStage::RESEARCH;
        $article->stage_status = ArticleStageStatus::PROCESSING;

        $idea = new Idea(
            (new \App\Contracts\IntentResolver\Intent)
                ->setTitle('AI copilots for engineering teams')
                ->setDescription('How teams improve velocity and quality with copilots.')
                ->setLanguage(Language::EN)
                ->setTemporal(Temporal::EVERGREEN)
                ->setTypes([IntentType::INFORMATIONAL])
        );

        $stageData = StageData::fromArray([]);
        $stageData->getIdeaStageData()->setPickedIdeaAuditReport(
            new IdeaAuditReport($idea, 0.8, ['Strong fit'], [])
        );
        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();

        $job = new class($client, $article->id) extends BuildArticleJob
        {
            public function runResearchStage(): ?bool
            {
                return $this->handleResearchStage();
            }
        };

        return [$article, $job];
    }

    protected function makeKeywordData(string $keyword): KeywordData
    {
        return (new KeywordData($keyword))
            ->setLanguage(Language::EN);
    }

    protected function makeIntentKeywordRow(string $keyword): object
    {
        $keywordData = $this->makeKeywordData($keyword);

        return new class($keywordData)
        {
            public function __construct(private KeywordData $keywordData)
            {
            }

            public function getKeyword(): KeywordData
            {
                return $this->keywordData;
            }
        };
    }
}
