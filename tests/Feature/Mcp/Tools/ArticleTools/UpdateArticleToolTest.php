<?php

namespace Tests\Feature\Mcp\Tools\ArticleTools;

use App\Contracts\Model\Article\Context as ArticleContext;
use App\Enums\ArticleStatus;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Mcp\Tools\ArticleTools\UpdateArticleContextTool;
use App\Mcp\Tools\ArticleTools\UpdateArticleTool;
use App\Models\Article;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class UpdateArticleToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_article_status_to_processing(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        Bus::fake();

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'status' => ArticleStatus::PROCESSING->value,
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the article')
            ->assertName('update-article-tool')
            ->assertDescription('Update an existing article. Use update-article-context-tool for semantic context fields.')
            ->assertStructuredContent(function ($json) use ($article): void {
                $json->where('id', $article->id)
                    ->where('status', ArticleStatus::PROCESSING->value)
                    ->etc();
            });

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => ArticleStatus::PROCESSING->value,
        ]);
    }

    public function test_updates_language_and_temporal(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'language' => Language::EN->value,
            'temporal' => Temporal::EVERGREEN->value,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->where('language', Language::EN->value)
                    ->where('temporal', Temporal::EVERGREEN->value)
                    ->etc();
            });

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'language' => Language::EN->value,
            'temporal' => Temporal::EVERGREEN->value,
        ]);
    }

    public function test_clears_language_and_temporal(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'language' => Language::EN->value,
            'temporal' => Temporal::TRENDING->value,
        ])->assertOk();

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'language' => null,
            'temporal' => null,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->where('language', null)
                    ->where('temporal', null)
                    ->etc();
            });

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'language' => null,
            'temporal' => null,
        ]);
    }

    public function test_does_not_modify_context(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'tone_of_voice' => 'Clear and practical',
            'meta' => ['raw_text' => 'Original brief'],
        ])->assertOk();

        $article->refresh();
        $originalContext = $article->context instanceof ArticleContext
            ? $article->context->toArray()
            : [];

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'language' => Language::FR->value,
        ]);

        $response->assertOk();

        $article->refresh();
        $this->assertSame(Language::FR, $article->language);
        $this->assertSame($originalContext, $article->context->toArray());
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'article_id' => '01J0000000000000000000000',
            'status' => ArticleStatus::PROCESSING->value,
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_article_id_is_missing(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $client->id,
            'status' => ArticleStatus::PROCESSING->value,
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_no_fields_to_update(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
        ]);

        $response->assertHasErrors([
            'Provide at least one field to update (status, language, or temporal).',
        ]);
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');
        $article = $this->createArticle($otherUser, $client);

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'status' => ArticleStatus::PROCESSING->value,
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => ArticleStatus::UNREADY->value,
        ]);
    }

    public function test_returns_error_when_article_belongs_to_different_client(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');
        $article = $this->createArticle($user, $secondClient);

        $response = AppServer::actingAs($user)->tool(UpdateArticleTool::class, [
            'client_id' => $firstClient->id,
            'article_id' => $article->id,
            'status' => ArticleStatus::PROCESSING->value,
        ]);

        $response->assertHasErrors(['Article not found or you do not have access to this article.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(UpdateArticleTool::class, [
            'client_id' => '01J0000000000000000000000',
            'article_id' => '01J0000000000000000000000',
            'status' => ArticleStatus::PROCESSING->value,
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

    private function createArticle(User $user, Client $client): Article
    {
        $response = AppServer::actingAs($user)->tool(CreateArticleTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertOk();

        $article = Article::query()->where('client_id', $client->id)->latest('id')->first();
        $this->assertNotNull($article);

        return $article;
    }
}
