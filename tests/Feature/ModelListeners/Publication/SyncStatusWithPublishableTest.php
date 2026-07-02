<?php

namespace Tests\Feature\ModelListeners\Publication;

use App\Enums\ArticleStatus;
use App\Enums\PlatformType;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Page;
use App\Models\Platform;
use App\Models\Publication;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SyncStatusWithPublishableTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_publication_to_pending_when_article_is_completed_and_publication_is_awaiting(): void
    {
        $article = $this->createArticle(ArticleStatus::READY);
        $publication = $this->createPublication($article, PublicationStatus::AWAITING);

        $publication->refresh();

        $this->assertSame(PublicationStatus::PENDING, $publication->status);
    }

    public function test_sets_publication_to_awaiting_when_article_is_not_completed_and_publication_is_not_awaiting(): void
    {
        $article = $this->createArticle(ArticleStatus::UNREADY);
        $publication = $this->createPublication($article, PublicationStatus::PENDING);

        $publication->refresh();

        $this->assertSame(PublicationStatus::AWAITING, $publication->status);
    }

    public function test_leaves_publication_unchanged_when_article_is_completed_and_publication_is_already_pending(): void
    {
        $article = $this->createArticle(ArticleStatus::READY);
        $publication = $this->createPublication($article, PublicationStatus::PENDING);

        $publication->title = 'Updated title';
        $publication->save();
        $publication->refresh();

        $this->assertSame(PublicationStatus::PENDING, $publication->status);
    }

    public function test_leaves_publication_unchanged_when_article_is_not_completed_and_publication_is_awaiting(): void
    {
        $article = $this->createArticle(ArticleStatus::UNREADY);
        $publication = $this->createPublication($article, PublicationStatus::AWAITING);

        $publication->title = 'Updated title';
        $publication->save();
        $publication->refresh();

        $this->assertSame(PublicationStatus::AWAITING, $publication->status);
    }

    #[DataProvider('retriablePublicationStatusesProvider')]
    public function test_does_not_change_retriable_publication_statuses(PublicationStatus $status): void
    {
        $article = $this->createArticle(ArticleStatus::READY);
        $publication = $this->createPublication($article, $status);

        $publication->attempts = 2;
        $publication->save();
        $publication->refresh();

        $this->assertSame($status, $publication->status);
        $this->assertSame(2, $publication->attempts);
    }

    public static function retriablePublicationStatusesProvider(): array
    {
        return [
            'timeout' => [PublicationStatus::TIMEOUT],
            'failed' => [PublicationStatus::FAILED],
            'error' => [PublicationStatus::ERROR],
        ];
    }

    public function test_throws_for_unhandled_publishable_type(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/'.uniqid()]);
        $url = 'https://example.com/page-'.uniqid();
        $page = Page::create([
            'source_id' => $source->id,
            'url' => $url,
            'url_hash' => sha1($url),
        ]);

        $client = new Client;
        $client->name = 'Acme Corp '.uniqid();
        $client->save();

        $platform = new Platform;
        $platform->name = 'Production FlyCMS '.uniqid();
        $platform->type = PlatformType::FLYCMS;
        $platform->save();

        $channel = new Channel;
        $channel->client()->associate($client);
        $channel->platform()->associate($platform);
        $channel->name = 'Blog';
        $channel->reference = uniqid();
        $channel->save();

        $publication = new Publication;
        $publication->channel()->associate($channel);
        $publication->publishable()->associate($page);
        $publication->status = PublicationStatus::AWAITING;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unhandled publishable type.');

        $publication->save();
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
        $channel->reference = uniqid();
        $channel->save();

        $publication = new Publication;
        $publication->channel()->associate($channel);
        $publication->publishable()->associate($article);
        $publication->status = $status;
        $publication->save();

        return $publication;
    }
}
