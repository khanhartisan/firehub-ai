<?php

namespace Tests\Feature\Mcp\Tools\AuthorTools;

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\AuthorTools\CreateAuthorTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAuthorToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_author_with_required_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $name = 'Editorial Lead';

        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'client_id' => $client->id,
            'name' => $name,
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new author')
            ->assertName('create-author-tool')
            ->assertDescription('Create a new author for a client.')
            ->assertStructuredContent(function ($json) use ($client, $name): void {
                $json->where('client_id', $client->id)
                    ->where('name', $name)
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });

        $this->assertDatabaseHas('authors', [
            'client_id' => $client->id,
            'name' => $name,
        ]);
    }

    public function test_creates_author_with_short_bio_and_bio(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $name = 'Editorial Lead';
        $shortBio = 'Writes about product strategy.';
        $bio = '<p>Seasoned editor with a decade of experience.</p>';

        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'client_id' => $client->id,
            'name' => $name,
            'short_bio' => $shortBio,
            'bio' => $bio,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($shortBio, $bio): void {
                $json->where('short_bio', $shortBio)
                    ->where('bio', $bio)
                    ->etc();
            });

        $this->assertDatabaseHas('authors', [
            'client_id' => $client->id,
            'name' => $name,
            'short_bio' => $shortBio,
            'bio' => $bio,
        ]);
    }

    public function test_creates_author_with_nullable_bio_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'client_id' => $client->id,
            'name' => 'Editorial Lead',
            'short_bio' => null,
            'bio' => null,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->where('short_bio', null)
                    ->where('bio', null)
                    ->etc();
            });

        $this->assertDatabaseHas('authors', [
            'client_id' => $client->id,
            'name' => 'Editorial Lead',
            'short_bio' => null,
            'bio' => null,
        ]);
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'name' => 'Editorial Lead',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('authors', 0);
    }

    public function test_validation_fails_when_name_is_missing(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('authors', 0);
    }

    public function test_returns_error_when_name_is_blank(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'client_id' => $client->id,
            'name' => '   ',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('authors', 0);
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'client_id' => $client->id,
            'name' => 'Editorial Lead',
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);

        $this->assertDatabaseCount('authors', 0);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(CreateAuthorTool::class, [
            'client_id' => '01J0000000000000000000000',
            'name' => 'Editorial Lead',
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
