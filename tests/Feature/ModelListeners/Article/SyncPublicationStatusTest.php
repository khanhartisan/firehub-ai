<?php

namespace Tests\Feature\ModelListeners\Article;

use App\Enums\ArticleStatus;
use App\Enums\PlatformType;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncPublicationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_awaiting_publications_to_pending_when_article_becomes_completed(): void
    {
        $article = $this->createArticle(ArticleStatus::UNREADY);
        $awaitingPublication = $this->createPublication($article, PublicationStatus::AWAITING);
        $alreadyPendingPublication = $this->createPublication($article, PublicationStatus::PENDING);

        $article->status = ArticleStatus::READY;
        $article->save();

        $awaitingPublication->refresh();
        $alreadyPendingPublication->refresh();

        $this->assertSame(PublicationStatus::PENDING, $awaitingPublication->status);
        $this->assertSame(PublicationStatus::PENDING, $alreadyPendingPublication->status);
    }

    public function test_sets_non_awaiting_publications_to_awaiting_when_article_becomes_not_completed(): void
    {
        $article = $this->createArticle(ArticleStatus::READY);
        $pendingPublication = $this->createPublication($article, PublicationStatus::PENDING);
        $awaitingPublication = $this->createPublication($article, PublicationStatus::AWAITING);

        $article->status = ArticleStatus::UNREADY;
        $article->save();

        $pendingPublication->refresh();
        $awaitingPublication->refresh();

        $this->assertSame(PublicationStatus::AWAITING, $pendingPublication->status);
        $this->assertSame(PublicationStatus::AWAITING, $awaitingPublication->status);
    }

    public function test_does_not_sync_publications_when_article_status_is_unchanged(): void
    {
        $article = $this->createArticle(ArticleStatus::UNREADY);
        $publication = $this->createPublication($article, PublicationStatus::AWAITING);

        $article->title = 'Updated title';
        $article->save();

        $publication->refresh();

        $this->assertSame(PublicationStatus::AWAITING, $publication->status);
    }

    public function test_only_updates_awaiting_publications_when_article_is_completed(): void
    {
        $article = $this->createArticle(ArticleStatus::UNREADY);
        $awaitingPublication = $this->createPublication($article, PublicationStatus::AWAITING);
        $publishedPublication = $this->createPublication($article, PublicationStatus::PUBLISHED);

        $article->status = ArticleStatus::PUBLISHED;
        $article->save();

        $awaitingPublication->refresh();
        $publishedPublication->refresh();

        $this->assertSame(PublicationStatus::PENDING, $awaitingPublication->status);
        $this->assertSame(PublicationStatus::PUBLISHED, $publishedPublication->status);
    }

    public function test_sets_all_non_awaiting_publications_to_awaiting_when_article_is_not_completed(): void
    {
        $article = $this->createArticle(ArticleStatus::READY);
        $pendingPublication = $this->createPublication($article, PublicationStatus::PENDING);
        $failedPublication = $this->createPublication($article, PublicationStatus::FAILED);

        $article->status = ArticleStatus::REJECTED;
        $article->save();

        $pendingPublication->refresh();
        $failedPublication->refresh();

        $this->assertSame(PublicationStatus::AWAITING, $pendingPublication->status);
        $this->assertSame(PublicationStatus::AWAITING, $failedPublication->status);
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

    private function createPublication(Article $article, PublicationStatus $status): Publication
    {
        $client = $article->client;
        $this->assertNotNull($client);

        $platform = new Platform;
        $platform->name = 'Production FlyCMS '.uniqid();
        $platform->type = PlatformType::FLYCMS;
        $platform->save();

        $channel = new Channel;
        $channel->client()->associate($client);
        $channel->platform()->associate($platform);
        $channel->name = 'Blog';
        $channel->save();

        $publication = new Publication;
        $publication->channel()->associate($channel);
        $publication->publishable()->associate($article);
        $publication->status = $status;
        $publication->save();

        if ($publication->status !== $status) {
            DB::table('publications')->where('id', $publication->id)->update([
                'status' => $status->value,
            ]);
            $publication->refresh();
        }

        return $publication;
    }
}
