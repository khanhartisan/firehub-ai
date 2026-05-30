<?php

namespace Tests\Feature\Mcp\Tools\AuthorTools;

use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Author\AuthorContexts\CognitiveContext;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\AuthorTools\CreateAuthorTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorContextTool;
use App\Models\Author;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateAuthorContextToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_cognitive_context(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $author = $this->createAuthor($user, $client, 'Editorial Lead');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorContextTool::class, [
            'author_id' => $author->id,
            'cognitive_context' => [
                'worldview' => 'Growth comes from disciplined experimentation.',
                'core_values' => ['Pragmatism', 'Meritocracy'],
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the author context')
            ->assertName('update-author-context-tool')
            ->assertDescription('Update the persona context of an existing author.')
            ->assertStructuredContent(function ($json) use ($author): void {
                $json->where('id', $author->id)->has('context')->etc();
            });

        $author->refresh();

        $cognitive = $author->context->getCognitiveContextValue();
        $this->assertIsArray($cognitive);
        $this->assertSame(
            'Growth comes from disciplined experimentation.',
            $cognitive['worldview']['value'] ?? null
        );
        $this->assertSame(
            ['Pragmatism', 'Meritocracy'],
            $cognitive['core_values']['value'] ?? null
        );
    }

    public function test_merges_with_existing_context_without_overwriting_unmentioned_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $author = $this->createAuthor($user, $client, 'Editorial Lead');
        $author->context = (new AuthorContext)
            ->setCognitiveContext(
                (new CognitiveContext)->setWorldview('Original worldview')
            );
        $author->save();

        $response = AppServer::actingAs($user)->tool(UpdateAuthorContextTool::class, [
            'author_id' => $author->id,
            'cognitive_context' => [
                'core_values' => ['Pragmatism'],
            ],
        ]);

        $response->assertOk();

        $author->refresh();

        $cognitive = $author->context->getCognitiveContextValue();
        $this->assertIsArray($cognitive);
        $this->assertSame('Original worldview', $cognitive['worldview']['value'] ?? null);
        $this->assertSame(['Pragmatism'], $cognitive['core_values']['value'] ?? null);
    }

    public function test_validation_fails_when_author_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(UpdateAuthorContextTool::class, [
            'cognitive_context' => [
                'worldview' => 'Missing author id.',
            ],
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_no_context_fields_to_update(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $author = $this->createAuthor($user, $client, 'Editorial Lead');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorContextTool::class, [
            'author_id' => $author->id,
        ]);

        $response->assertHasErrors(['Provide at least one context field to update.']);
    }

    public function test_returns_error_when_author_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');
        $author = $this->createAuthor($otherUser, $client, 'Other Author');

        $response = AppServer::actingAs($user)->tool(UpdateAuthorContextTool::class, [
            'author_id' => $author->id,
            'cognitive_context' => [
                'worldview' => 'Should not apply.',
            ],
        ]);

        $response->assertHasErrors(['Author not found or you do not have access to this author.']);

        $author->refresh();
        $this->assertNull($author->context->getCognitiveContextValue());
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(UpdateAuthorContextTool::class, [
            'author_id' => '01J0000000000000000000000',
            'cognitive_context' => [
                'worldview' => 'No auth.',
            ],
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
