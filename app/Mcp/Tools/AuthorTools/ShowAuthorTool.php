<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show details of an existing author.')]
class ShowAuthorTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'author_id' => ['required', 'string'],
        ]);

        $user = McpAccess::user($request);
        $author = McpAccess::author($user, (string) $request->get('author_id'));

        return McpResponse::details('Author', $author->toMcpDetailStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'author_id' => $schema->string()
                ->description('The ULID of the author to show')
                ->required(),
        ];
    }
}
