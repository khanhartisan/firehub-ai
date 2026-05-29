<?php

namespace Tests\Feature\Mcp\Tools\ClientTools;

use App\Enums\Language;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ClientTools\CreateClientTool;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateClientToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_client_with_required_name(): void
    {
        $name = 'Acme Corp';

        $response = AppServer::tool(CreateClientTool::class, [
            'name' => $name,
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new client')
            ->assertName('create-client-tool')
            ->assertDescription('Create a new client.')
            ->assertStructuredContent(function ($json) use ($name): void {
                $json->where('name', $name)
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });

        $this->assertDatabaseHas('clients', [
            'name' => $name,
        ]);
    }

    public function test_creates_client_with_language(): void
    {
        $name = 'Global Media';

        $response = AppServer::tool(CreateClientTool::class, [
            'name' => $name,
            'language' => Language::EN->value,
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new client')
            ->assertStructuredContent(function ($json) use ($name): void {
                $json->where('name', $name)->etc();
            });

        $client = Client::query()->where('name', $name)->first();

        $this->assertNotNull($client);
        $this->assertSame(Language::EN, $client->language);
    }

    public function test_validation_fails_when_name_is_missing(): void
    {
        $response = AppServer::tool(CreateClientTool::class, []);

        $response->assertHasErrors();

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_validation_fails_when_name_is_too_short(): void
    {
        $response = AppServer::tool(CreateClientTool::class, [
            'name' => 'Acme',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_validation_fails_when_name_is_too_long(): void
    {
        $response = AppServer::tool(CreateClientTool::class, [
            'name' => str_repeat('a', 51),
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('clients', 0);
    }
}
