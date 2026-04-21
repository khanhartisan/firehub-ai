<?php

namespace Tests\Feature\Models;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Client\GeneralContext;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * {@see Article::$stage_data} is hydrated via {@see \App\Casts\ArticleStageDataCast}.
 * Build pipeline code mutates that DTO in place (same reference as the model’s cast cache),
 * then persists with {@see \App\Jobs\BuildArticleJob::touchArticleQuietly()}.
 */
class ArticleStageDataSelfReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_data_is_shared_reference_and_mutations_persist_after_save_quietly(): void
    {
        $client = $this->makeClient();
        $article = $this->makeArticle($client);

        $article->refresh();

        $stageData = $article->stage_data;
        $this->assertInstanceOf(StageData::class, $stageData);

        $this->assertSame($stageData, $article->stage_data, 'Repeated reads should return the cached StageData instance (class cast cache).');

        $markerTitle = 'self-ref-brief-title-'.str()->ulid();
        $brief = (new Brief)->setTitle($markerTitle);
        $stageData->setBrief($brief);

        $this->assertSame($stageData, $article->stage_data);
        $this->assertSame($markerTitle, $article->stage_data->getBrief()?->getTitle());

        $article->updated_at = now();
        $article->saveQuietly();

        $reloadedArticle = Article::query()->findOrFail($article->getKey());
        $this->assertNotSame($article, $reloadedArticle, 'find() must return a new Article instance, not the mutated in-memory model.');

        $reloaded = $reloadedArticle->stage_data;
        $this->assertInstanceOf(StageData::class, $reloaded);
        $this->assertNotSame($stageData, $reloaded, 'A new StageData instance is hydrated from the DB for the new Article instance.');
        $this->assertSame($markerTitle, $reloaded->getBrief()?->getTitle());
    }

    protected function makeClient(): Client
    {
        $client = new Client;
        $client->name = 'client-'.str()->ulid();
        $client->general_context = (new GeneralContext)
            ->setDescription('Test context for StageData reference.');
        $client->save();

        return $client;
    }

    protected function makeArticle(Client $client): Article
    {
        $article = new Article;
        $article->client()->associate($client);
        $article->context = 'Article context.';
        $article->status = ArticleStatus::UNREADY;
        $article->stage = ArticleStage::IDEA;
        $article->stage_status = ArticleStageStatus::PENDING;
        $article->save();

        return $article;
    }
}
