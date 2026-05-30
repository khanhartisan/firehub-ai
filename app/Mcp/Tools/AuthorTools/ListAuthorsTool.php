<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Models\Author;
use App\Models\User;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show the list of authors that belong to the current user\'s clients.')]
class ListAuthorsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'client_id' => ['sometimes', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = Author::query()
            ->whereHas('client.users', fn ($query) => $query->where('users.id', $user->id))
            ->orderBy('name');

        if ($request->exists('client_id')) {
            $clientId = (string) $request->get('client_id');

            if (! $user->clients()->where('clients.id', $clientId)->exists()) {
                return Response::error('Client not found or you do not have access to this client.');
            }

            $query->where('client_id', $clientId);
        }

        /** @var Collection<int, Author> $authors */
        $authors = $query->get();

        if ($authors->isEmpty()) {
            return Response::error('No authors found.');
        }

        $authorsData = $authors
            ->map(fn (Author $author) => $author->toMcpStructuredData())
            ->values()
            ->toArray();

        return Response::make(
            Response::text('Found '.$authors->count().' '.Str::plural('author', $authors->count()).":\n\n".json_encode($authorsData))
        )->withStructuredContent([
            'authors' => $authorsData,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()
                ->description('Optional ULID to filter authors by client'),
        ];
    }
}
