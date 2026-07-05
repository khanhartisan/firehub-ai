<?php

namespace Tests\Feature\ModelListeners\Article;

use App\Enums\ArticleStatus;
use App\Jobs\BuildArticleJob;
use App\Models\Article;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DispatchBuildJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.max_article_building_queue_size', 100);
    }

    public function test_dispatches_build_article_job_when_status_changes_to_processing(): void
    {
        $article = $this->createArticle(ArticleStatus::READY);

        Bus::fake();

        $article->status = ArticleStatus::PROCESSING;
        $article->save();

        Bus::assertDispatched(BuildArticleJob::class, function (BuildArticleJob $job) use ($article): bool {
            return $this->getJobArticleId($job) === $article->id;
        });
    }

    public function test_does_not_dispatch_when_status_is_unchanged(): void
    {
        $article = $this->createArticle(ArticleStatus::PROCESSING);

        Bus::fake();

        $article->title = 'Updated title';
        $article->save();

        Bus::assertNotDispatched(BuildArticleJob::class);
    }

    public function test_does_not_dispatch_when_status_changes_to_non_processing(): void
    {
        $article = $this->createArticle(ArticleStatus::PROCESSING);

        Bus::fake();

        $article->status = ArticleStatus::READY;
        $article->save();

        Bus::assertNotDispatched(BuildArticleJob::class);
    }

    public function test_does_not_dispatch_when_article_building_queue_is_full(): void
    {
        Config::set('queue.max_article_building_queue_size', 0);

        $article = $this->createArticle(ArticleStatus::READY);

        Bus::fake();

        $article->status = ArticleStatus::PROCESSING;
        $article->save();

        Bus::assertNotDispatched(BuildArticleJob::class);
    }

    private function createArticle(ArticleStatus $status): Article
    {
        $client = new Client;
        $client->name = 'Acme Corp '.uniqid();
        $client->save();

        $article = new Article;
        $article->client()->associate($client);
        $article->status = $status;
        $article->save();

        return $article;
    }

    private function getJobArticleId(BuildArticleJob $job): string
    {
        $property = new \ReflectionProperty($job, 'article');
        $property->setAccessible(true);

        /** @var Article $article */
        $article = $property->getValue($job);

        return $article->id;
    }
}
