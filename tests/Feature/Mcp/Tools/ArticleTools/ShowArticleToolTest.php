<?php

namespace Tests\Feature\Mcp\Tools\ArticleTools;

use App\Contracts\Model\Article\Context as ArticleContext;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Mcp\Tools\ArticleTools\ShowArticleTool;
use App\Models\Article;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowArticleToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_article_details(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(ShowArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Article details')
            ->assertName('show-article-tool')
            ->assertDescription('Show details of an existing article.')
            ->assertStructuredContent(function ($json) use ($article, $client): void {
                $json->where('id', $article->id)
                    ->where('client_id', $client->id)
                    ->where('status', ArticleStatus::UNREADY->value)
                    ->where('stage', ArticleStage::IDEA->value)
                    ->where('stage_status', ArticleStageStatus::PENDING->value)
                    ->where('attempts', 0)
                    ->where('intents_count', 0)
                    ->has('context')
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });
    }

    public function test_includes_article_context_in_structured_content(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        $article->context = (new ArticleContext)->setMeta(['raw_text' => 'Target audience: technical founders.']);
        $article->save();
        $article->refresh();

        $this->assertSame(
            'Target audience: technical founders.',
            $article->context->getMetaValue()['raw_text'] ?? null
        );

        $response = AppServer::actingAs($user)->tool(ShowArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->has('context.meta')
                    ->where('context.meta.value.raw_text', 'Target audience: technical founders.')
                    ->etc();
            });
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowArticleTool::class, [
            'article_id' => '01J0000000000000000000000',
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_article_id_is_missing(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(ShowArticleTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');
        $article = $this->createArticle($otherUser, $client);

        $response = AppServer::actingAs($user)->tool(ShowArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);
    }

    public function test_returns_error_when_article_does_not_exist(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(ShowArticleTool::class, [
            'client_id' => $client->id,
            'article_id' => '01J0000000000000000000000',
        ]);

        $response->assertHasErrors(['Article not found or you do not have access to this article.']);
    }

    public function test_returns_error_when_article_belongs_to_different_client(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');
        $article = $this->createArticle($user, $secondClient);

        $response = AppServer::actingAs($user)->tool(ShowArticleTool::class, [
            'client_id' => $firstClient->id,
            'article_id' => $article->id,
        ]);

        $response->assertHasErrors(['Article not found or you do not have access to this article.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ShowArticleTool::class, [
            'client_id' => '01J0000000000000000000000',
            'article_id' => '01J0000000000000000000000',
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

        $article = Article::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($article);

        return $article;
    }
}
