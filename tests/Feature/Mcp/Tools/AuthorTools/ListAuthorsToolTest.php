<?php

namespace Tests\Feature\Mcp\Tools\AuthorTools;

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\AuthorTools\CreateAuthorTool;
use App\Mcp\Tools\AuthorTools\ListAuthorsTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListAuthorsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_authors_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $this->createAuthor($user, $client, 'Editorial Lead');
        $this->createAuthor($user, $client, 'Pragmatic Founder');

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 2 authors')
            ->assertName('list-authors-tool')
            ->assertDescription('Show the list of authors that belong to the current user\'s clients.')
            ->assertStructuredContent(function ($json): void {
                $json->has('authors', 2)
                    ->where('authors', fn (mixed $authors): bool => collect($authors)
                        ->pluck('name')
                        ->sort()
                        ->values()
                        ->all() === ['Editorial Lead', 'Pragmatic Founder'])
                    ->etc();
            });
    }

    public function test_uses_singular_author_label_for_single_result(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $this->createAuthor($user, $client, 'Solo Author');

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 1 author')
            ->assertStructuredContent(function ($json): void {
                $json->has('authors', 1)
                    ->where('authors.0.name', 'Solo Author')
                    ->etc();
            });
    }

    public function test_returns_error_when_user_has_no_authors(): void
    {
        $user = User::factory()->create();
        $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class);

        $response->assertHasErrors(['No authors found.']);
    }

    public function test_does_not_list_other_users_authors(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myClient = $this->attachClient($user, 'My Client');
        $otherClient = $this->attachClient($otherUser, 'Other Client');

        $this->createAuthor($user, $myClient, 'My Author');
        $this->createAuthor($otherUser, $otherClient, 'Other Author');

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 1 author')
            ->assertStructuredContent(function ($json): void {
                $json->has('authors', 1)
                    ->where('authors.0.name', 'My Author')
                    ->etc();
            });
    }

    public function test_lists_authors_across_multiple_clients(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');

        $this->createAuthor($user, $firstClient, 'Acme Author');
        $this->createAuthor($user, $secondClient, 'Global Author');

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 2 authors')
            ->assertStructuredContent(function ($json): void {
                $json->has('authors', 2)
                    ->where('authors', fn (mixed $authors): bool => collect($authors)
                        ->pluck('name')
                        ->sort()
                        ->values()
                        ->all() === ['Acme Author', 'Global Author'])
                    ->etc();
            });
    }

    public function test_filters_authors_by_client_id(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');

        $this->createAuthor($user, $firstClient, 'Acme Author');
        $this->createAuthor($user, $secondClient, 'Global Author');

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class, [
            'client_id' => $firstClient->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 author')
            ->assertStructuredContent(function ($json) use ($firstClient): void {
                $json->has('authors', 1)
                    ->where('authors.0.name', 'Acme Author')
                    ->where('authors.0.client_id', $firstClient->id)
                    ->etc();
            });
    }

    public function test_returns_error_when_filtering_by_inaccessible_client(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherClient = $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class, [
            'client_id' => $otherClient->id,
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);
    }

    public function test_returns_error_when_filtering_by_client_with_no_authors(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertHasErrors(['No authors found.']);
    }

    public function test_includes_author_fields_in_structured_content(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $shortBio = 'Writes about product strategy.';
        $bio = '<p>Seasoned editor with a decade of experience.</p>';
        $this->createAuthor($user, $client, 'Editorial Lead', $shortBio, $bio);

        $response = AppServer::actingAs($user)->tool(ListAuthorsTool::class);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($client, $shortBio, $bio): void {
                $json->has('authors', 1)
                    ->where('authors.0.name', 'Editorial Lead')
                    ->where('authors.0.client_id', $client->id)
                    ->where('authors.0.short_bio', $shortBio)
                    ->where('authors.0.bio', $bio)
                    ->has('authors.0.created_at')
                    ->has('authors.0.updated_at')
                    ->etc();
            });
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ListAuthorsTool::class);

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

    private function createAuthor(
        User $user,
        Client $client,
        string $name,
        ?string $shortBio = null,
        ?string $bio = null,
    ): void {
        $response = AppServer::actingAs($user)->tool(CreateAuthorTool::class, [
            'client_id' => $client->id,
            'name' => $name,
            'short_bio' => $shortBio,
            'bio' => $bio,
        ]);

        $response->assertOk();
    }
}
