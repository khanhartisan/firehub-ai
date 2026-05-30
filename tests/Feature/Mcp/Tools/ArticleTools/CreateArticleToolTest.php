<?php

namespace Tests\Feature\Mcp\Tools\ArticleTools;

use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateArticleToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_article_with_client_id(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(CreateArticleTool::class, [
            'client_id' => $client->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new article')
            ->assertName('create-article-tool')
            ->assertDescription('Create a new article for a client.')
            ->assertStructuredContent(function ($json) use ($client): void {
                $json->where('client_id', $client->id)
                    ->where('status', ArticleStatus::UNREADY->value)
                    ->where('stage', ArticleStage::IDEA->value)
                    ->where('stage_status', ArticleStageStatus::PENDING->value)
                    ->where('attempts', 0)
                    ->where('intents_count', 0)
                    ->where('language', null)
                    ->where('temporal', null)
                    ->has('id')
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });

        $this->assertDatabaseHas('articles', [
            'client_id' => $client->id,
            'status' => ArticleStatus::UNREADY->value,
            'stage' => ArticleStage::IDEA->value,
            'stage_status' => ArticleStageStatus::PENDING->value,
            'attempts' => 0,
            'intents_count' => 0,
        ]);
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(CreateArticleTool::class, []);

        $response->assertHasErrors();

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(CreateArticleTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(CreateArticleTool::class, [
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
}
