<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Models\Author;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new author for a client.')]
class CreateAuthorTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        if ($request->has('name')) {
            $request->merge(['name' => trim((string) $request->get('name'))]);
        }

        $request->validate([
            'client_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = McpAccess::user($request);
        $client = McpAccess::client($user, (string) $request->get('client_id'));

        $author = new Author;
        $author->client()->associate($client);
        $author->name = (string) $request->get('name');

        DB::transaction(function () use ($author): void {
            $author->save();
        });

        $author->refresh();

        return McpResponse::created('author', $author->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()
                ->description('The ULID of the client to create the author for')
                ->required(),
            'name' => $schema->string()
                ->description('Author display name')
                ->required(),
        ];
    }
}
