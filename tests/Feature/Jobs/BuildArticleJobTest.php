<?php

namespace Tests\Feature\Jobs;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Model\Article\StageData\IdeaStageData\AdvisorData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\IntentResolver\Intent;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\Language;
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
            (new BuildArticleJob($client, $article->id))->handle();
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
        $this->assertNotEmpty($article->body_markdown);
        $this->assertInstanceOf(StageData::class, $article->stage_data);
        $stageData = $article->stage_data->toArray();
        $this->assertArrayHasKey('idea', $stageData);
        $this->assertArrayHasKey('brief', $stageData);
        $this->assertArrayHasKey('outline', $stageData);
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
            (new BuildArticleJob($client, $article->id))->handle();
            $article->refresh();
            if ($article->status === ArticleStatus::READY) {
                break;
            }
        }

        $article->refresh();
        $this->assertSame(ArticleStatus::READY, $article->status);
        $this->assertSame(ArticleStage::FINAL, $article->stage);
        $this->assertSame(ArticleStageStatus::APPROVED, $article->stage_status);
        $this->assertNotEmpty($article->body_markdown);
    }

    public function test_builds_article_when_intent_resolver_mixes_merge_and_no_merge(): void
    {
        Bus::fake();

        IntentResolver::shouldReceive('mergeIntents')
            ->andReturnUsing(static fn ($left, $right) => random_int(0, 1) === 1 ? $right : null);

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        for ($i = 0; $i < 500; $i++) {
            (new BuildArticleJob($client, $article->id))->handle();
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

        (new BuildArticleJob($client, $article->id))->handle();

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

        (new BuildArticleJob($client, $article->id))->handle();

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

        (new BuildArticleJob($client, $article->id))->handle();

        $article->refresh();
        $this->assertSame(ArticleStatus::UNREADY, $article->status);
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

        (new BuildArticleJob($client, $article->id))->handle();

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
            (new BuildArticleJob($client, $article->id))->handle();
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

        $job = new class($client, $article->id) extends BuildArticleJob
        {
            protected function runCurrentStage(): ?bool
            {
                throw new \RuntimeException('Synthetic build failure for testing.');
            }
        };
        $jobClass = $job::class;

        $job->handle();

        $article->refresh();
        $this->assertSame(ArticleStatus::UNREADY, $article->status);
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

        $job = new class($client, $article->id) extends BuildArticleJob
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
        $this->assertLessThanOrEqual(10 * 1024, strlen((string) $article->error_logs));
        Bus::assertNotDispatched($jobClass);
    }

    protected function makeClient(string $context): Client
    {
        $client = new Client;
        $client->name = 'client-'.str()->ulid();
        $client->context = $context;
        $client->save();

        return $client;
    }

    protected function makeArticle(Client $client, string $context): Article
    {
        $article = new Article;
        $article->client()->associate($client);
        $article->context = $context;
        $article->status = ArticleStatus::UNREADY;
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

        $stageData = $article->stage_data instanceof StageData ? $article->stage_data : StageData::fromArray([]);
        $ideaData = $stageData->getIdeaStageData();
        $ideaData->setIdeas($ideas);
        $ideaData->setUniqueIdeaIdentifierPairs([]);
        $ideaData->setUniquenessIndex(0);

        $advisorData = new AdvisorData;
        $advisorData->setIdeas($ideas);
        $ideaData->setAdvisorDataByIdentifier('merge-test-advisor', $advisorData);

        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();

        $job = new class($client, $article->id) extends BuildArticleJob
        {
            public function runMergeOnce(): ?bool
            {
                return $this->processIntentMerging();
            }
        };

        return [$article, $job];
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
