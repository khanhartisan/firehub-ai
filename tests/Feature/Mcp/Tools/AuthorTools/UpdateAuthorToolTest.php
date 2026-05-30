<?php

namespace Tests\Feature\Mcp\Tools\AuthorTools;

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\AuthorTools\CreateAuthorTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorContextTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorTool;
use App\Models\Author;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateAuthorToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_author_name(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $author = $this->createAuthor($user, $client, 'Editorial Lead');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
            'name' => 'Senior Editor',
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the author')
            ->assertName('update-author-tool')
            ->assertDescription('Update an existing author.')
            ->assertStructuredContent(function ($json): void {
                $json->where('name', 'Senior Editor')->etc();
            });

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'Senior Editor',
        ]);
    }

    public function test_updates_author_client(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');
        $author = $this->createAuthor($user, $firstClient, 'Editorial Lead');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
            'client_id' => $secondClient->id,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($secondClient): void {
                $json->where('client_id', $secondClient->id)->etc();
            });

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'client_id' => $secondClient->id,
        ]);
    }

    public function test_updates_name_and_client_together(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');
        $author = $this->createAuthor($user, $firstClient, 'Editorial Lead');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
            'name' => 'Global Editor',
            'client_id' => $secondClient->id,
        ]);

        $response->assertOk();

        $author->refresh();
        $this->assertSame('Global Editor', $author->name);
        $this->assertSame($secondClient->id, $author->client_id);
    }

    public function test_does_not_modify_context(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $author = $this->createAuthor($user, $client, 'Editorial Lead');

        AppServer::actingAs($user)->tool(UpdateAuthorContextTool::class, [
            'author_id' => $author->id,
            'cognitive_context' => [
                'worldview' => 'Growth comes from disciplined experimentation.',
            ],
        ]);

        $author->refresh();
        $originalContext = $author->context->toArray();

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
            'name' => 'Renamed Editor',
        ]);

        $response->assertOk();

        $author->refresh();
        $this->assertSame('Renamed Editor', $author->name);
        $this->assertSame($originalContext, $author->context->toArray());
    }

    public function test_validation_fails_when_author_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'name' => 'Valid Name',
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_no_fields_to_update(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $author = $this->createAuthor($user, $client, 'Editorial Lead');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
        ]);

        $response->assertHasErrors(['Provide at least one field to update (name or client_id).']);
    }

    public function test_validation_fails_when_name_is_blank(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $author = $this->createAuthor($user, $client, 'Editorial Lead');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
            'name' => '   ',
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_author_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');
        $author = $this->createAuthor($otherUser, $client, 'Other Author');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
            'name' => 'Stolen Name',
        ]);

        $response->assertHasErrors(['Author not found or you do not have access to this author.']);

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'Other Author',
        ]);
    }

    public function test_returns_error_when_target_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($user, 'My Client');
        $otherClient = $this->attachClient($otherUser, 'Other Client');
        $author = $this->createAuthor($user, $client, 'My Author');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
            'client_id' => $otherClient->id,
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(UpdateAuthorTool::class, [
            'author_id' => '01J0000000000000000000000',
            'name' => 'Valid Name',
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

    private function createAuthor(User $user, Client $client, string $name): Author
    {
        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'client_id' => $client->id,
            'name' => $name,
        ]);

        $response->assertOk();

        $author = Author::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($author);

        return $author;
    }
}
