<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show details of an existing article.')]
class ShowArticleTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'article_id' => ['required', 'string'],
        ]);

        $user = McpAccess::user($request);
        $article = McpAccess::article(
            $user,
            (string) $request->get('client_id'),
            (string) $request->get('article_id'),
        );

        return McpResponse::details('Article', $article->toMcpDetailStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()
                ->description('The ULID of the client that owns the article')
                ->required(),
            'article_id' => $schema->string()
                ->description('The ULID of the article to show')
                ->required(),
        ];
    }
}
