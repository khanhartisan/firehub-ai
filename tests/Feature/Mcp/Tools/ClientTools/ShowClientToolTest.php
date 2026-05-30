<?php

namespace Tests\Feature\Mcp\Tools\ClientTools;

use App\Enums\Language;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ClientTools\ShowClientTool;
use App\Mcp\Tools\ClientTools\UpdateClientContextTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowClientToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_client_details(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp', Language::EN);

        AppServer::actingAs($user)->tool(UpdateClientContextTool::class, [
            'client_id' => $client->id,
            'description' => 'AI automation consulting platform.',
            'industry' => 'Technology',
        ]);

        $response = AppServer::actingAs($user)->tool(ShowClientTool::class, [
            'client_id' => $client->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Client details')
            ->assertName('show-client-tool')
            ->assertDescription('Show details of an existing client.')
            ->assertStructuredContent(function ($json) use ($client): void {
                $json->where('id', $client->id)
                    ->where('name', 'Acme Corp')
                    ->has('language')
                    ->has('context')
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowClientTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(ShowClientTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);
    }

    public function test_returns_error_when_client_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowClientTool::class, [
            'client_id' => '01J0000000000000000000000',
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ShowClientTool::class, [
            'client_id' => '01J0000000000000000000000',
        ]);

        $response->assertHasErrors(['Unauthenticated.']);
    }

    private function attachClient(User $user, string $name, ?Language $language = null): Client
    {
        $client = new Client;
        $client->name = $name;
        $client->language = $language;
        $client->save();

        $user->clients()->attach($client);

        return $client;
    }
}
