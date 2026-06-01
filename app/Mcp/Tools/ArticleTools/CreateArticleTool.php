<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Models\Article;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new article for a client.')]
class CreateArticleTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['required', 'string'],
        ]);

        $user = McpAccess::user($request);
        $client = McpAccess::client($user, (string) $request->get('client_id'));

        $article = new Article;
        $article->client()->associate($client);

        DB::transaction(function () use ($article): void {
            $article->save();
        });

        $article->refresh();

        return McpResponse::created('article', $article->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()
                ->description('The ULID of the client to create the article for')
                ->required(),
        ];
    }
}
