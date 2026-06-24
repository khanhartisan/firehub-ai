<?php

namespace Tests\Feature\Mcp\Tools\ArticleTools;

use App\Contracts\Model\Article\Context as ArticleContext;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Mcp\Tools\ArticleTools\UpdateArticleContextTool;
use App\Models\Article;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateArticleContextToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_meta_context(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'meta' => ['raw_text' => 'Target audience: technical founders.'],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the article context')
            ->assertName('update-article-context-tool')
            ->assertDescription('Update the semantic context of an existing article. This tool is optional, if the context is empty, system will automatically fulfill.')
            ->assertStructuredContent(function ($json) use ($article, $client): void {
                $json->where('id', $article->id)
                    ->where('client_id', $client->id)
                    ->has('context.meta')
                    ->etc();
            });

        $article->refresh();
        $this->assertSame(
            'Target audience: technical founders.',
            $article->context->getMetaValue()['raw_text'] ?? null
        );
    }

    public function test_updates_scalar_and_array_context_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'tone_of_voice' => 'Clear and practical',
            'guidelines' => ['Avoid hype', 'Use concrete examples'],
            'idea_guidelines' => ['Focus on actionable takeaways'],
        ]);

        $response->assertOk();

        $article->refresh();
        $this->assertSame('Clear and practical', $article->context->getToneOfVoiceValue());
        $this->assertSame(['Avoid hype', 'Use concrete examples'], $article->context->getGuidelinesValue());
        $this->assertSame(['Focus on actionable takeaways'], $article->context->getIdeaGuidelinesValue());
    }

    public function test_merges_with_existing_context_without_overwriting_unmentioned_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);
        $article->context = (new ArticleContext)
            ->setToneOfVoice('Original tone')
            ->setMeta(['raw_text' => 'Original brief']);
        $article->save();

        $response = AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'guidelines' => ['Keep paragraphs short'],
        ]);

        $response->assertOk();

        $article->refresh();
        $this->assertSame('Original tone', $article->context->getToneOfVoiceValue());
        $this->assertSame('Original brief', $article->context->getMetaValue()['raw_text'] ?? null);
        $this->assertSame(['Keep paragraphs short'], $article->context->getGuidelinesValue());
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'article_id' => '01J0000000000000000000000',
            'meta' => ['raw_text' => 'Missing client id.'],
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_article_id_is_missing(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'client_id' => $client->id,
            'meta' => ['raw_text' => 'Missing article id.'],
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_no_context_fields_to_update(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $article = $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
        ]);

        $response->assertHasErrors(['Provide at least one context field to update.']);
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');
        $article = $this->createArticle($otherUser, $client);

        $response = AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'client_id' => $client->id,
            'article_id' => $article->id,
            'meta' => ['raw_text' => 'Should not apply.'],
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);

        $article->refresh();
        $this->assertNull($article->context->getMetaValue()['raw_text'] ?? null);
    }

    public function test_returns_error_when_article_belongs_to_different_client(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');
        $article = $this->createArticle($user, $secondClient);

        $response = AppServer::actingAs($user)->tool(UpdateArticleContextTool::class, [
            'client_id' => $firstClient->id,
            'article_id' => $article->id,
            'meta' => ['raw_text' => 'Should not apply.'],
        ]);

        $response->assertHasErrors(['Article not found or you do not have access to this article.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(UpdateArticleContextTool::class, [
            'client_id' => '01J0000000000000000000000',
            'article_id' => '01J0000000000000000000000',
            'meta' => ['raw_text' => 'No auth.'],
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
