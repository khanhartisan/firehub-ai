<?php

namespace Tests\Feature\Mcp\Tools\ArticleTools;

use App\Enums\ArticleStatus;
use App\Enums\PlatformType;
use App\Enums\PublicationStatus;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Mcp\Tools\ArticleTools\PublishArticleTool;
use App\Models\Article;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishArticleToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_publications_for_multiple_channels(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $firstChannel = $this->createChannel($client, $platform, 'Blog');
        $secondChannel = $this->createChannel($client, $platform, 'News');
        $article = $this->createArticle($user, $client);
        $article->title = 'Launch announcement';
        $article->excerpt = 'We are launching today.';
        $article->status = ArticleStatus::READY;
        $article->save();

        $response = AppServer::actingAs($user)->tool(PublishArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'channel_ids' => [$firstChannel->id, $secondChannel->id],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created publications')
            ->assertName('publish-article-tool')
            ->assertDescription('Publish an article to one or more channels by creating publications.')
            ->assertStructuredContent(function ($json) use ($firstChannel, $secondChannel, $article): void {
                $json->has('publications', 2)
                    ->where('publications.0.channel_id', $firstChannel->id)
                    ->where('publications.0.publishable_type', 'article')
                    ->where('publications.0.publishable_id', $article->id)
                    ->where('publications.0.title', 'Launch announcement')
                    ->where('publications.0.description', 'We are launching today.')
                    ->where('publications.0.status', PublicationStatus::PENDING->value)
                    ->where('publications.1.channel_id', $secondChannel->id)
                    ->where('publications.1.status', PublicationStatus::PENDING->value)
                    ->etc();
            });

        $this->assertDatabaseHas('publications', [
            'channel_id' => $firstChannel->id,
            'publishable_type' => 'article',
            'publishable_id' => $article->id,
            'title' => 'Launch announcement',
            'description' => 'We are launching today.',
            'status' => PublicationStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas('publications', [
            'channel_id' => $secondChannel->id,
            'publishable_type' => 'article',
            'publishable_id' => $article->id,
            'status' => PublicationStatus::PENDING->value,
        ]);

        $firstChannel->refresh();
        $secondChannel->refresh();
        $this->assertSame(1, $firstChannel->publications_count);
        $this->assertSame(1, $secondChannel->publications_count);
    }

    public function test_creates_awaiting_publication_when_article_is_not_ready(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($client, $platform, 'Blog');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(PublishArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'channel_ids' => [$channel->id],
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->where('publications.0.status', PublicationStatus::AWAITING->value)->etc();
            });

        $this->assertDatabaseHas('publications', [
            'channel_id' => $channel->id,
            'publishable_type' => 'article',
            'publishable_id' => $article->id,
            'status' => PublicationStatus::AWAITING->value,
        ]);
    }

    public function test_validation_fails_when_channel_ids_is_missing(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(PublishArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('publications', 0);
    }

    public function test_returns_error_when_channel_does_not_belong_to_client(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $otherClient = $this->attachClient($user, 'Other Client');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $otherChannel = $this->createChannel($otherClient, $platform, 'Other Blog');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(PublishArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'channel_ids' => [$otherChannel->id],
        ]);

        $response->assertHasErrors(['Channel(s) do not belong to this client: '.$otherChannel->id.'.']);

        $this->assertDatabaseCount('publications', 0);
    }

    public function test_ignores_existing_publication_and_returns_it(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($client, $platform, 'Blog');
        $article = $this->createArticle($user, $client);

        $existingPublication = new Publication;
        $existingPublication->channel()->associate($channel);
        $existingPublication->publishable()->associate($article);
        $existingPublication->status = PublicationStatus::AWAITING;
        $existingPublication->save();

        $response = AppServer::actingAs($user)->tool(PublishArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'channel_ids' => [$channel->id],
        ]);

        $response
            ->assertOk()
            ->assertSee('All publications already exist')
            ->assertStructuredContent(function ($json) use ($existingPublication, $channel): void {
                $json->has('publications', 1)
                    ->where('publications.0.id', $existingPublication->id)
                    ->where('publications.0.channel_id', $channel->id)
                    ->where('publications.0.status', PublicationStatus::AWAITING->value)
                    ->etc();
            });

        $this->assertDatabaseCount('publications', 1);
    }

    public function test_resets_retriable_publication_to_awaiting_with_zero_attempts(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($client, $platform, 'Blog');
        $article = $this->createArticle($user, $client);

        $existingPublication = new Publication;
        $existingPublication->channel()->associate($channel);
        $existingPublication->publishable()->associate($article);
        $existingPublication->status = PublicationStatus::FAILED;
        $existingPublication->attempts = 3;
        $existingPublication->save();

        $response = AppServer::actingAs($user)->tool(PublishArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'channel_ids' => [$channel->id],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created publications')
            ->assertStructuredContent(function ($json) use ($existingPublication, $channel): void {
                $json->has('publications', 1)
                    ->where('publications.0.id', $existingPublication->id)
                    ->where('publications.0.channel_id', $channel->id)
                    ->where('publications.0.status', PublicationStatus::AWAITING->value)
                    ->etc();
            });

        $this->assertDatabaseHas('publications', [
            'id' => $existingPublication->id,
            'status' => PublicationStatus::AWAITING->value,
            'attempts' => 0,
        ]);
        $this->assertDatabaseCount('publications', 1);
    }

    public function test_creates_only_missing_publications_when_some_already_exist(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $existingChannel = $this->createChannel($client, $platform, 'Blog');
        $newChannel = $this->createChannel($client, $platform, 'News');
        $article = $this->createArticle($user, $client);

        $existingPublication = new Publication;
        $existingPublication->channel()->associate($existingChannel);
        $existingPublication->publishable()->associate($article);
        $existingPublication->status = PublicationStatus::AWAITING;
        $existingPublication->save();

        $response = AppServer::actingAs($user)->tool(PublishArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'channel_ids' => [$existingChannel->id, $newChannel->id],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created publications')
            ->assertStructuredContent(function ($json) use ($existingPublication, $existingChannel, $newChannel): void {
                $json->has('publications', 2)
                    ->where('publications.0.id', $existingPublication->id)
                    ->where('publications.0.channel_id', $existingChannel->id)
                    ->where('publications.1.channel_id', $newChannel->id)
                    ->where('publications.1.status', PublicationStatus::AWAITING->value)
                    ->etc();
            });

        $this->assertDatabaseCount('publications', 2);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(PublishArticleTool::class, [
            'client_id' => '01J0000000000000000000000',
            'article_id' => '01J0000000000000000000001',
            'channel_ids' => ['01J0000000000000000000002'],
        ]);

        $response->assertHasErrors(['Unauthenticated.']);
    }

    private function attachClient(User $user, string $name): Client
    {
        $client = new Client;
        $client->name = $name;
        $client->save();

        $user->clients()->attach($client);

        return $client;
    }

    private function createPlatform(string $name, PlatformType $type): Platform
    {
        $platform = new Platform;
        $platform->name = $name;
        $platform->type = $type;
        $platform->save();

        return $platform;
    }

    private function createChannel(Client $client, Platform $platform, string $name): Channel
    {
        $channel = new Channel;
        $channel->client()->associate($client);
        $channel->platform()->associate($platform);
        $channel->name = $name;
        $channel->reference = uniqid();
        $channel->save();

        return $channel;
    }

    private function createArticle(User $user, Client $client): Article
    {
        $response = AppServer::actingAs($user)->tool(CreateArticleTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertOk();

        $article = Article::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($article);

        return $article;
    }
}
