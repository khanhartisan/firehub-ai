<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use App\Models\Author;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show the list of authors that belong to the current user\'s clients.')]
class ListAuthorsTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['sometimes', 'string'],
        ]);

        $user = McpAccess::user($request);

        $query = Author::query()
            ->accessibleBy($user)
            ->orderBy('name');

        if ($request->exists('client_id')) {
            $clientId = (string) $request->get('client_id');

            McpAccess::assertClientAccess($user, $clientId);

            $query->where('client_id', $clientId);
        }

        /** @var Collection<int, Author> $authors */
        $authors = $query->get();

        if ($authors->isEmpty()) {
            throw new McpToolException('No authors found.');
        }

        $authorsData = $authors
            ->map(fn (Author $author) => $author->toMcpStructuredData())
            ->values()
            ->toArray();

        return McpResponse::list('author', $authorsData, 'authors');
    }

    /**
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
