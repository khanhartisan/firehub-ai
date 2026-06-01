<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing author.')]
class UpdateAuthorTool extends Tool
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
            'author_id' => ['required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_id' => ['sometimes', 'required', 'string'],
        ]);

        if (! $request->exists('name') && ! $request->exists('client_id')) {
            throw new McpToolException('Provide at least one field to update (name or client_id).');
        }

        $user = McpAccess::user($request);
        $author = McpAccess::author($user, (string) $request->get('author_id'));

        if ($request->exists('client_id')) {
            $client = McpAccess::client($user, (string) $request->get('client_id'));
            $author->client()->associate($client);
        }

        if ($request->exists('name')) {
            $author->name = (string) $request->get('name');
        }

        DB::transaction(function () use ($author): void {
            $author->save();
        });

        $author->refresh();

        return McpResponse::updated('author', $author->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'author_id' => $schema->string()
                ->description('The ULID of the author to update')
                ->required(),
            'name' => $schema->string()
                ->description('Author display name'),
            'client_id' => $schema->string()
                ->description('The ULID of the client to move the author to'),
        ];
    }
}
