<?php

namespace Tests\Feature\Mcp\Tools\ClientTools;

use App\Enums\Language;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ClientTools\ListClientsTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListClientsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_clients_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->attachClient($user, 'Acme Corp');
        $this->attachClient($user, 'Global Media');

        $response = AppServer::actingAs($user)->tool(ListClientsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 2 clients')
            ->assertName('list-clients-tool')
            ->assertDescription('Show the list of clients that belong to the current user.')
            ->assertStructuredContent(function ($json): void {
                $json->has('clients', 2)
                    ->where('clients', fn (mixed $clients): bool => collect($clients)
                        ->pluck('name')
                        ->sort()
                        ->values()
                        ->all() === ['Acme Corp', 'Global Media'])
                    ->etc();
            });
    }

    public function test_uses_singular_client_label_for_single_result(): void
    {
        $user = User::factory()->create();
        $this->attachClient($user, 'Solo Client');

        $response = AppServer::actingAs($user)->tool(ListClientsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 1 client')
            ->assertStructuredContent(function ($json): void {
                $json->has('clients', 1)
                    ->where('clients.0.name', 'Solo Client')
                    ->etc();
            });
    }

    public function test_returns_error_when_user_has_no_clients(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ListClientsTool::class);

        $response->assertHasErrors(['No clients found.']);
    }

    public function test_does_not_list_other_users_clients(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->attachClient($user, 'My Client');
        $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(ListClientsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 1 client')
            ->assertStructuredContent(function ($json): void {
                $json->has('clients', 1)
                    ->where('clients.0.name', 'My Client')
                    ->etc();
            });
    }

    public function test_includes_client_language_in_structured_content(): void
    {
        $user = User::factory()->create();
        $this->attachClient($user, 'Localized Client', Language::EN);

        $response = AppServer::actingAs($user)->tool(ListClientsTool::class);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->has('clients', 1)
                    ->where('clients.0.name', 'Localized Client')
                    ->has('clients.0.created_at')
                    ->has('clients.0.updated_at')
                    ->etc();
            });
    }

    public function test_fails_when_unauthenticated(): void
    {
        $this->expectException(\ErrorException::class);

        AppServer::tool(ListClientsTool::class);
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
