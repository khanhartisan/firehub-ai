<?php

namespace Tests\Feature\Mcp\Tools\ClientTools;

use App\Enums\Language;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ClientTools\UpdateClientTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateClientToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_client_name(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'client_id' => $client->id,
            'name' => 'Acme Corporation',
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the client')
            ->assertName('update-client-tool')
            ->assertDescription('Update an existing client.')
            ->assertStructuredContent(function ($json): void {
                $json->where('name', 'Acme Corporation')->etc();
            });

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Acme Corporation',
        ]);
    }

    public function test_updates_client_language(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Global Media');

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'client_id' => $client->id,
            'language' => Language::EN->value,
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the client')
            ->assertStructuredContent(function ($json): void {
                $json->where('name', 'Global Media')->etc();
            });

        $client->refresh();
        $this->assertSame(Language::EN, $client->language);
    }

    public function test_updates_name_and_language_together(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Old Name');

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'client_id' => $client->id,
            'name' => 'New Brand Name',
            'language' => Language::FR->value,
        ]);

        $response->assertOk();

        $client->refresh();
        $this->assertSame('New Brand Name', $client->name);
        $this->assertSame(Language::FR, $client->language);
    }

    public function test_can_clear_language(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Localized Client', Language::EN);

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'client_id' => $client->id,
            'language' => null,
        ]);

        $response->assertOk();

        $client->refresh();
        $this->assertNull($client->language);
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'name' => 'Valid Name',
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_no_fields_to_update(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertHasErrors(['Provide at least one field to update (name or language).']);
    }

    public function test_validation_fails_when_name_is_too_short(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'client_id' => $client->id,
            'name' => 'Acme',
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'client_id' => $client->id,
            'name' => 'Stolen Name',
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Other Client',
        ]);
    }

    public function test_returns_error_when_client_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(UpdateClientTool::class, [
            'client_id' => '01J0000000000000000000000',
            'name' => 'Valid Name',
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(UpdateClientTool::class, [
            'client_id' => '01J0000000000000000000000',
            'name' => 'Valid Name',
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
