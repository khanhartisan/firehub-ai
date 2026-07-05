<?php

namespace Tests\Feature\Jobs;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\Model\Article\Context as ArticleContext;
use App\Contracts\Model\Client\Context;
use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Model\Article\StageData\IdeaStageData\AdvisorData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Contracts\IntentResolver\Intent;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Facades\IntentResolver;
use App\Jobs\BuildArticleJob;
use App\Models\Article;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BuildArticleJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_builds_article_through_all_stages_and_marks_ready(): void
    {
        Bus::fake();
        $this->mockIntentResolverNeverMerge();

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        for ($i = 0; $i < 500; $i++) {
            new BuildArticleJob($article)->handle();
            $article->refresh();
            if ($article->status === ArticleStatus::READY) {
                break;
            }
        }

        $article->refresh();
        $this->assertSame(ArticleStatus::READY, $article->status);
        $this->assertSame(ArticleStage::FINAL, $article->stage);
        $this->assertSame(ArticleStageStatus::APPROVED, $article->stage_status);
        $this->assertNotNull($article->temporal);
        $this->assertNotEmpty($article->title);
        $this->assertNotEmpty($article->excerpt);
        $this->assertInstanceOf(DOMArticle::class, $article->article);
        $this->assertNotEmpty($article->article->toHtml());
        $this->assertInstanceOf(StageData::class, $article->stage_data);
        $stageData = $article->stage_data->toArray();
        $this->assertArrayHasKey('idea', $stageData);
        $this->assertArrayHasKey('brief', $stageData);
        $this->assertArrayHasKey('outline', $stageData);
        $this->assertArrayHasKey('tagging', $stageData);
        $this->assertNotEmpty($stageData['tagging']['suggested_tags'] ?? []);
        $article->load('tags');
        $this->assertSame(
            $stageData['tagging']['suggested_tags'],
            $article->tags->pluck('name')->values()->all(),
        );
        $this->assertArrayHasKey('draft', $stageData);
        Bus::assertDispatched(BuildArticleJob::class);
    }

    public function test_builds_article_when_intent_resolver_always_merges(): void
    {
        Bus::fake();

        IntentResolver::shouldReceive('mergeIntents')
            ->andReturnUsing(static fn ($left, $right) => $right);

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        for ($i = 0; $i < 500; $i++) {
            (new BuildArticleJob($article))->handle();
            $article->refresh();
            if ($article->status === ArticleStatus::READY) {
                break;
            }
        }

        $article->refresh();
        $this->assertSame(ArticleStatus::READY, $article->status);
        $this->assertSame(ArticleStage::FINAL, $article->stage);
        $this->assertSame(ArticleStageStatus::APPROVED, $article->stage_status);
        $this->assertInstanceOf(DOMArticle::class, $article->article);
        $this->assertNotEmpty($article->article->toHtml());
    }

    public function test_builds_article_when_intent_resolver_mixes_merge_and_no_merge(): void
    {
        Bus::fake();

        IntentResolver::shouldReceive('mergeIntents')
            ->andReturnUsing(static fn ($left, $right) => random_int(0, 1) === 1 ? $right : null);

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        for ($i = 0; $i < 500; $i++) {
            (new BuildArticleJob($article))->handle();
            $article->refresh();
            if ($article->status === ArticleStatus::READY) {
                break;
            }
        }

        $article->refresh();
        $this->assertSame(ArticleStatus::READY, $article->status);
        $this->assertSame(ArticleStage::FINAL, $article->stage);
        $this->assertSame(ArticleStageStatus::APPROVED, $article->stage_status);
    }

    public function test_selects_top_suggestions_using_advisor_weights(): void
    {
        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Weighted selection test.');

        // Alpha: weight 1, Beta: weight 3.
        // Assertions verify unique suggestion aggregation by weighted average across all advisors.
        $alpha = new WeightedStubIdeaAdvisor('weighted-alpha', 1.0);
        $beta = new WeightedStubIdeaAdvisor('weighted-beta', 3.0);

        $stageData = $this->ensureStageData($article);
        $ideaData = $stageData->getIdeaStageData();

        $alphaData = new AdvisorData;
        $alphaData->setTemporalSuggestions([
            new TemporalSuggestion(Temporal::TOPICAL, 0.9, 'alpha-topical'),
            new TemporalSuggestion(Temporal::EVERGREEN, 0.1, 'alpha-evergreen'),
        ]);
        $alphaData->setIntentTypeSuggestions([
            new IntentTypeSuggestion(IntentType::INFORMATIONAL, 0.2, 'alpha-informational'),
            new IntentTypeSuggestion(IntentType::COMMERCIAL, 0.05, 'alpha-commercial'),
        ]);
        $ideaData->setAdvisorDataByIdentifier('weighted-alpha', $alphaData);

        $betaData = new AdvisorData;
        $betaData->setTemporalSuggestions([
            new TemporalSuggestion(Temporal::TOPICAL, 0.2, 'beta-topical'),
            new TemporalSuggestion(Temporal::EVERGREEN, 0.2, 'beta-evergreen'),
        ]);
        $betaData->setIntentTypeSuggestions([
            new IntentTypeSuggestion(IntentType::INFORMATIONAL, 0.1, 'beta-informational'),
            new IntentTypeSuggestion(IntentType::COMMERCIAL, 0.1, 'beta-commercial'),
        ]);
        $ideaData->setAdvisorDataByIdentifier('weighted-beta', $betaData);

        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();

        // Topical avg=(0.9*1 + 0.2*3) / (1+3)=0.375, Evergreen avg=(0.1*1 + 0.2*3)/4=0.175.
        // Commercial avg=(0.05*1 + 0.1*3)/4=0.0875, Informational avg=(0.2*1 + 0.1*3)/4=0.125.
        $job = new class($article, [$alpha, $beta]) extends BuildArticleJob
        {
            /**
             * @param  \App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor[]  $stubAdvisors
             */
            public function __construct(Article $article, private array $stubAdvisors)
            {
                parent::__construct($article);
            }

            protected function getIdeaAdvisors(): array
            {
                return $this->stubAdvisors;
            }

            public function runSelectTopSuggestions(): bool
            {
                return $this->selectTopSuggestions();
            }
        };

        $this->assertTrue($job->runSelectTopSuggestions());
        $article->refresh();
        $selected = $article->stage_data->getIdeaStageData();

        $selectedTemporals = array_values($selected->getSelectedTemporalSuggestions());
        $selectedIntentTypes = array_values($selected->getSelectedIntentTypeSuggestions());

        $this->assertCount(2, $selectedTemporals);
        $this->assertCount(2, $selectedIntentTypes);

        $temporalsByType = [];
        foreach ($selectedTemporals as $suggestion) {
            $temporalsByType[$suggestion->getTemporal()->value] = $suggestion;
        }
        $intentTypesByType = [];
        foreach ($selectedIntentTypes as $suggestion) {
            $intentTypesByType[$suggestion->getIntentType()->value] = $suggestion;
        }

        $this->assertEqualsWithDelta(0.375, $temporalsByType[Temporal::TOPICAL->value]->getConfidence(), 0.0001);
        $this->assertEqualsWithDelta(0.175, $temporalsByType[Temporal::EVERGREEN->value]->getConfidence(), 0.0001);
        $this->assertEqualsWithDelta(0.125, $intentTypesByType[IntentType::INFORMATIONAL->value]->getConfidence(), 0.0001);
        $this->assertEqualsWithDelta(0.0875, $intentTypesByType[IntentType::COMMERCIAL->value]->getConfidence(), 0.0001);
        $this->assertSame('alpha-topical', $temporalsByType[Temporal::TOPICAL->value]->getReason());
        $this->assertSame('beta-evergreen', $temporalsByType[Temporal::EVERGREEN->value]->getReason());
        $this->assertSame('beta-informational', $intentTypesByType[IntentType::INFORMATIONAL->value]->getReason());
        $this->assertSame('beta-commercial', $intentTypesByType[IntentType::COMMERCIAL->value]->getReason());
    }

    public function test_process_intent_merging_with_always_merge_strategy(): void
    {
        IntentResolver::shouldReceive('mergeIntents')
            ->andReturnUsing(static fn ($left, $right) => $right);

        [$article, $job] = $this->makeArticleAndJobWithIdeaPool();

        $result = null;
        for ($i = 0; $i < 20; $i++) {
            $result = $job->runMergeOnce();
            $article->refresh();
            if ($result === true) {
                break;
            }
        }

        $ideaData = $article->stage_data->getIdeaStageData();
        $this->assertTrue($result === true);
        $this->assertCount(1, $ideaData->getIdeas());
    }

    public function test_process_intent_merging_with_mixed_merge_strategy(): void
    {
        $callCount = 0;
        IntentResolver::shouldReceive('mergeIntents')
            ->andReturnUsing(static function ($left, $right) use (&$callCount) {
                $callCount++;

                return $callCount % 2 === 0 ? $right : null;
            });

        [$article, $job] = $this->makeArticleAndJobWithIdeaPool();

        $result = null;
        for ($i = 0; $i < 30; $i++) {
            $result = $job->runMergeOnce();
            $article->refresh();
            if ($result === true) {
                break;
            }
        }

        $ideaData = $article->stage_data->getIdeaStageData();
        $this->assertTrue($result === true);
        $this->assertGreaterThan(0, $callCount);
        $this->assertGreaterThanOrEqual(1, count($ideaData->getIdeas()));
        $this->assertLessThanOrEqual(3, count($ideaData->getIdeas()));
    }

    public function test_marks_article_failed_when_idea_stage_cannot_generate_candidates(): void
    {
        Bus::fake();
        $this->mockIntentResolverNeverMerge();

        $client = $this->makeClient('');
        $article = $this->makeArticle($client, '');

        (new BuildArticleJob($article))->handle();

        $article->refresh();

        $this->assertSame(ArticleStatus::FAILED, $article->status);
        $this->assertSame(ArticleStage::IDEA, $article->stage);
        $this->assertSame(ArticleStageStatus::REJECTED, $article->stage_status);
        Bus::assertNotDispatched(BuildArticleJob::class);
    }

    public function test_does_not_process_article_when_not_unready(): void
    {
        Bus::fake();
        $this->mockIntentResolverNeverMerge();

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Context for article.');
        $article->status = ArticleStatus::READY;
        $article->save();

        (new BuildArticleJob($article))->handle();

        $article->refresh();

        $this->assertSame(ArticleStatus::READY, $article->status);
        $this->assertSame(ArticleStage::IDEA, $article->stage);
        $this->assertSame(ArticleStageStatus::PENDING, $article->stage_status);
        $this->assertInstanceOf(StageData::class, $article->stage_data);
        $this->assertSame([], $article->stage_data->toArray());
        Bus::assertNotDispatched(BuildArticleJob::class);
    }

    public function test_processes_single_stage_per_execution_and_re_dispatches(): void
    {
        Bus::fake();
        $this->mockIntentResolverNeverMerge();

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        (new BuildArticleJob($article))->handle();

        $article->refresh();
        $this->assertSame(ArticleStatus::PROCESSING, $article->status);
        $this->assertSame(ArticleStage::IDEA, $article->stage);
        $this->assertSame(ArticleStageStatus::PROCESSING, $article->stage_status);

        $this->assertInstanceOf(StageData::class, $article->stage_data);
        $stageData = $article->stage_data->toArray();
        $this->assertArrayHasKey('idea', $stageData);
        $this->assertArrayHasKey('advisors', $stageData['idea']);
        $this->assertArrayNotHasKey('brief', $stageData);

        Bus::assertDispatchedTimes(BuildArticleJob::class, 1);
    }

    public function test_persists_newly_initialized_advisor_data_on_first_idea_checkpoint(): void
    {
        Bus::fake();
        $this->mockIntentResolverNeverMerge();

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        (new BuildArticleJob($article))->handle();

        $article->refresh();
        $this->assertInstanceOf(StageData::class, $article->stage_data);

        $advisors = data_get($article->stage_data->toArray(), 'idea.advisors', []);
        $this->assertNotEmpty($advisors);
        $firstAdvisor = collect($advisors)->first();
        $this->assertIsArray($firstAdvisor);
        $this->assertArrayHasKey('advisor_description', $firstAdvisor);
        $this->assertIsString($firstAdvisor['advisor_description']);
        $this->assertNotSame('', trim($firstAdvisor['advisor_description']));
    }

    public function test_persists_idea_stage_data_consistently_across_reloads(): void
    {
        Bus::fake();
        $this->mockIntentResolverNeverMerge();

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        for ($i = 0; $i < 25; $i++) {
            (new BuildArticleJob($article))->handle();
            $article->refresh();

            $this->assertInstanceOf(StageData::class, $article->stage_data);
            $reloaded = Article::query()->findOrFail($article->id);
            $this->assertInstanceOf(StageData::class, $reloaded->stage_data);
            $this->assertSame($article->stage_data->toArray(), $reloaded->stage_data->toArray());

            $advisors = data_get($article->stage_data->toArray(), 'idea.advisors', []);
            foreach ($advisors as $advisorData) {
                if (! is_array($advisorData)) {
                    continue;
                }

                $this->assertIsString($advisorData['advisor_description'] ?? null);
                $this->assertNotSame('', trim((string) ($advisorData['advisor_description'] ?? '')));
            }

            if ($article->stage !== ArticleStage::IDEA) {
                break;
            }
        }
    }

    public function test_retries_and_logs_error_when_exception_happens(): void
    {
        Bus::fake();
        $this->mockIntentResolverNeverMerge();

        config()->set('queue.max_article_build_attempts', 3);

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        $job = new class($article) extends BuildArticleJob
        {
            protected function runCurrentStage(): ?bool
            {
                throw new \RuntimeException('Synthetic build failure for testing.');
            }
        };
        $jobClass = $job::class;

        $job->handle();

        $article->refresh();
        $this->assertSame(ArticleStatus::PROCESSING, $article->status);
        $this->assertSame(ArticleStageStatus::PENDING, $article->stage_status);
        $this->assertSame(1, $article->attempts);
        $this->assertNotNull($article->error_logs);
        $this->assertStringContainsString('Synthetic build failure for testing.', (string) $article->error_logs);
        Bus::assertDispatchedTimes($jobClass, 1);
    }

    public function test_marks_article_error_when_max_attempts_is_reached(): void
    {
        Bus::fake();
        $this->mockIntentResolverNeverMerge();

        config()->set('queue.max_article_build_attempts', 1);

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        $job = new class($article) extends BuildArticleJob
        {
            protected function runCurrentStage(): ?bool
            {
                throw new \RuntimeException(str_repeat('X', 15000));
            }
        };
        $jobClass = $job::class;

        $job->handle();

        $article->refresh();
        $this->assertSame(ArticleStatus::ERROR, $article->status);
        $this->assertSame(ArticleStageStatus::REJECTED, $article->stage_status);
        $this->assertSame(1, $article->attempts);
        $this->assertNotNull($article->error_logs);
        $this->assertLessThanOrEqual(20 * 1024, strlen((string) $article->error_logs));
        Bus::assertNotDispatched($jobClass);
    }

    protected function makeClient(string $context): Client
    {
        $client = new Client;
        $client->name = 'client-'.str()->ulid();
        $client->context = (new Context)
            ->setDescription($context);
        $client->save();

        return $client;
    }

    protected function makeArticle(Client $client, string $context): Article
    {
        $article = new Article;
        $article->client()->associate($client);
        $article->context = (new ArticleContext)->setMeta(['raw_text' => $context]);
        $article->status = ArticleStatus::PROCESSING;
        $article->stage = ArticleStage::IDEA;
        $article->stage_status = ArticleStageStatus::PENDING;
        $article->save();

        return $article;
    }

    protected function mockIntentResolverNeverMerge(): void
    {
        IntentResolver::shouldReceive('mergeIntents')
            ->andReturn(null);
    }

    /**
     * @return array{0: Article, 1: BuildArticleJob}
     */
    protected function makeArticleAndJobWithIdeaPool(): array
    {
        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Context for merge testing.');

        $ideas = [
            $this->makeIdea('idea-a'),
            $this->makeIdea('idea-b'),
            $this->makeIdea('idea-c'),
        ];

        $stageData = $this->ensureStageData($article);
        $ideaData = $stageData->getIdeaStageData();
        $ideaData->setIdeas($ideas);
        $ideaData->setUniqueIdeaIdentifierPairs([]);

        $advisorData = new AdvisorData;
        $advisorData->setIdeas($ideas);
        $ideaData->setAdvisorDataByIdentifier('merge-test-advisor', $advisorData);

        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();

        $job = new class($article) extends BuildArticleJob
        {
            public function runMergeOnce(): ?bool
            {
                return $this->processIntentMerging();
            }
        };

        return [$article, $job];
    }

    /**
     * Mirrors job {@see \App\Jobs\BuildArticleJobConcerns\InteractsWithArticleStageData::getStageData()}
     * without constructing a job (test setup only).
     */
    protected function ensureStageData(Article $article): StageData
    {
        if ($article->stage_data instanceof StageData) {
            return $article->stage_data;
        }

        $article->stage_data = StageData::fromArray([]);

        return $article->stage_data;
    }

    protected function makeIdea(string $title): Idea
    {
        $intent = new Intent;
        $intent->setTitle($title);
        $intent->setDescription('Description for '.$title);
        $intent->setLanguage(Language::EN);

        return new Idea($intent, 0.8, 'test');
    }
}

final class WeightedStubIdeaAdvisor extends \App\Services\Synthesizer\IdeaForge\IdeaAdvisor\IdeaAdvisorService
{
    public function __construct(string $identifier, float $weight)
    {
        $this->setIdentifier($identifier);
        $this->setDescription('Weighted stub advisor for tests.');
        $this->setWeight($weight);
    }

    public function suggestTemporal(string $clientId, SemanticContext $context): array
    {
        return [];
    }

    public function suggestIntentTypes(string $clientId, SemanticContext $context): array
    {
        return [];
    }

    public function brainstorm(
        array $temporalSuggestions,
        array $intentTypeSuggestions,
        SemanticContext $context,
        int $limit = 5
    ): array {
        return [];
    }
}
