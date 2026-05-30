<?php

namespace Tests\Feature\Mcp\Tools\ArticleTools;

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Mcp\Tools\ArticleTools\ListArticlesTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListArticlesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_articles_for_client(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $this->createArticle($user, $client);
        $this->createArticle($user, $client);

        $response = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $client->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Showing 2 articles')
            ->assertName('list-articles-tool')
            ->assertDescription('List articles that belong to the current user\'s client, with pagination.')
            ->assertStructuredContent(function ($json) use ($client): void {
                $json->has('articles', 2)
                    ->where('articles.0.client_id', $client->id)
                    ->where('articles.1.client_id', $client->id)
                    ->where('pagination.current_page', 1)
                    ->where('pagination.per_page', 15)
                    ->where('pagination.total', 2)
                    ->where('pagination.last_page', 1)
                    ->etc();
            });
    }

    public function test_paginates_articles(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $this->createArticle($user, $client);
        $this->createArticle($user, $client);
        $this->createArticle($user, $client);

        $firstPage = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $client->id,
            'per_page' => 2,
            'page' => 1,
        ]);

        $firstPage
            ->assertOk()
            ->assertSee('page 1 of 2')
            ->assertStructuredContent(function ($json): void {
                $json->has('articles', 2)
                    ->where('pagination.current_page', 1)
                    ->where('pagination.per_page', 2)
                    ->where('pagination.total', 3)
                    ->where('pagination.last_page', 2)
                    ->etc();
            });

        $secondPage = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $client->id,
            'per_page' => 2,
            'page' => 2,
        ]);

        $secondPage
            ->assertOk()
            ->assertSee('page 2 of 2')
            ->assertStructuredContent(function ($json): void {
                $json->has('articles', 1)
                    ->where('pagination.current_page', 2)
                    ->where('pagination.total', 3)
                    ->etc();
            });
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ListArticlesTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_client_has_no_articles(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertHasErrors(['No articles found.']);
    }

    public function test_does_not_list_other_users_articles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myClient = $this->attachClient($user, 'My Client');
        $otherClient = $this->attachClient($otherUser, 'Other Client');

        $this->createArticle($user, $myClient);
        $this->createArticle($otherUser, $otherClient);

        $response = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $myClient->id,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->has('articles', 1)
                    ->where('pagination.total', 1)
                    ->etc();
            });
    }

    public function test_lists_only_articles_for_requested_client(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');

        $this->createArticle($user, $firstClient);
        $this->createArticle($user, $secondClient);

        $response = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $firstClient->id,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($firstClient): void {
                $json->has('articles', 1)
                    ->where('articles.0.client_id', $firstClient->id)
                    ->where('pagination.total', 1)
                    ->etc();
            });
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherClient = $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $otherClient->id,
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);
    }

    public function test_validation_fails_when_page_is_invalid(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $client->id,
            'page' => 0,
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_per_page_exceeds_maximum(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(ListArticlesTool::class, [
            'client_id' => $client->id,
            'per_page' => 101,
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ListArticlesTool::class, [
            'client_id' => '01J0000000000000000000000',
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

    private function createArticle(User $user, Client $client): void
    {
        $response = AppServer::actingAs($user)->tool(CreateArticleTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertOk();
    }
}
