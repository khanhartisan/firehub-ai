<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Models\Article;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show details of an existing article.')]
class ShowArticleTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'article_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $clientId = (string) $request->get('client_id');

        if (! $user->clients()->where('clients.id', $clientId)->exists()) {
            return Response::error('Client not found or you do not have access to this client.');
        }

        /** @var Article|null $article */
        $article = Article::query()
            ->where('client_id', $clientId)
            ->where('id', $request->get('article_id'))
            ->first();

        if ($article === null) {
            return Response::error('Article not found or you do not have access to this article.');
        }

        $data = $article->toMcpDetailStructuredData();

        return Response::make(Response::text('Article details:'."\n\n".json_encode($data)))
            ->withStructuredContent($data);
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
                ->description('The ULID of the client that owns the article')
                ->required(),
            'article_id' => $schema->string()
                ->description('The ULID of the article to show')
                ->required(),
        ];
    }
}
