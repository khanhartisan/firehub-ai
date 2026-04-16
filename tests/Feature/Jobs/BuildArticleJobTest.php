<?php

namespace Tests\Feature\Jobs;

use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Jobs\BuildArticleJob;
use App\Models\Article;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BuildArticleJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_article_through_all_stages_and_marks_ready(): void
    {
        Bus::fake();

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        (new BuildArticleJob($client, $article->id))->handle();
        (new BuildArticleJob($client, $article->id))->handle();
        (new BuildArticleJob($client, $article->id))->handle();
        (new BuildArticleJob($client, $article->id))->handle();
        (new BuildArticleJob($client, $article->id))->handle();

        $article->refresh();

        $this->assertSame(ArticleStatus::READY, $article->status);
        $this->assertSame(ArticleStage::FINAL, $article->stage);
        $this->assertSame(ArticleStageStatus::APPROVED, $article->stage_status);
        $this->assertNotNull($article->temporal);
        $this->assertNotEmpty($article->title);
        $this->assertNotEmpty($article->excerpt);
        $this->assertNotEmpty($article->body_markdown);
        $stageData = $article->stage_data;
        $this->assertIsArray($stageData);
        $this->assertArrayHasKey('idea', $stageData);
        $this->assertArrayHasKey('brief', $stageData);
        $this->assertArrayHasKey('outline', $stageData);
        $this->assertArrayHasKey('draft', $stageData);
        Bus::assertDispatched(BuildArticleJob::class);
    }

    public function test_marks_article_failed_when_idea_stage_cannot_generate_candidates(): void
    {
        Bus::fake();

        $client = $this->makeClient('Client context');
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

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Context for article.');
        $article->status = ArticleStatus::READY;
        $article->save();

        (new BuildArticleJob($client, $article->id))->handle();

        $article->refresh();

        $this->assertSame(ArticleStatus::READY, $article->status);
        $this->assertSame(ArticleStage::IDEA, $article->stage);
        $this->assertSame(ArticleStageStatus::PENDING, $article->stage_status);
        $this->assertNull($article->stage_data);
        Bus::assertNotDispatched(BuildArticleJob::class);
    }

    public function test_processes_single_stage_per_execution_and_re_dispatches(): void
    {
        Bus::fake();

        $client = $this->makeClient('Client context');
        $article = $this->makeArticle($client, 'Build me an article from this context.');

        (new BuildArticleJob($client, $article->id))->handle();

        $article->refresh();
        $this->assertSame(ArticleStatus::UNREADY, $article->status);
        $this->assertSame(ArticleStage::BRIEF, $article->stage);
        $this->assertSame(ArticleStageStatus::PENDING, $article->stage_status);

        $stageData = $article->stage_data;
        $this->assertIsArray($stageData);
        $this->assertArrayHasKey('idea', $stageData);
        $this->assertArrayNotHasKey('brief', $stageData);

        Bus::assertDispatchedTimes(BuildArticleJob::class, 1);
    }

    public function test_retries_and_logs_error_when_exception_happens(): void
    {
        Bus::fake();

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
}
