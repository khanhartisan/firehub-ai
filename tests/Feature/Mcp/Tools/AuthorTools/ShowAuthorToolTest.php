<?php

namespace Tests\Feature\Mcp\Tools\AuthorTools;

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\AuthorTools\CreateAuthorTool;
use App\Mcp\Tools\AuthorTools\ShowAuthorTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorContextTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorTool;
use App\Models\Author;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowAuthorToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_author_bio_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $author = $this->createAuthor($user, $client, 'Editorial Lead');
        $shortBio = 'Writes about product strategy.';
        $bio = '<p>Seasoned editor with a decade of experience.</p>';

        AppServer::actingAs($user)->tool(UpdateAuthorTool::class, [
            'author_id' => $author->id,
            'short_bio' => $shortBio,
            'bio' => $bio,
        ])->assertOk();

        $response = AppServer::actingAs($user)->tool(ShowAuthorTool::class, [
            'author_id' => $author->id,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($shortBio, $bio): void {
                $json->where('short_bio', $shortBio)
                    ->where('bio', $bio)
                    ->etc();
            });
    }

    public function test_shows_author_details(): void
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

        $response = AppServer::actingAs($user)->tool(ShowAuthorTool::class, [
            'author_id' => $author->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Author details')
            ->assertName('show-author-tool')
            ->assertDescription('Show details of an existing author.')
            ->assertStructuredContent(function ($json) use ($author, $client): void {
                $json->where('id', $author->id)
                    ->where('client_id', $client->id)
                    ->where('name', 'Editorial Lead')
                    ->has('context')
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });
    }

    public function test_validation_fails_when_author_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowAuthorTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_author_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');
        $author = $this->createAuthor($otherUser, $client, 'Other Author');

        $response = AppServer::actingAs($user)->tool(ShowAuthorTool::class, [
            'author_id' => $author->id,
        ]);

        $response->assertHasErrors(['Author not found or you do not have access to this author.']);
    }

    public function test_returns_error_when_author_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowAuthorTool::class, [
            'author_id' => '01J0000000000000000000000',
        ]);

        $response->assertHasErrors(['Author not found or you do not have access to this author.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ShowAuthorTool::class, [
            'author_id' => '01J0000000000000000000000',
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
