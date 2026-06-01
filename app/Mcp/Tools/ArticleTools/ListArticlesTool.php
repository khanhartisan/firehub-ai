<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAuthorization;
use App\Mcp\Support\McpResponse;
use App\Models\Article;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List articles that belong to the current user\'s client, with pagination.')]
class ListArticlesTool extends Tool
{
    private const int DEFAULT_PER_PAGE = 15;

    private const int MAX_PER_PAGE = 100;

    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $user = McpAuthorization::user($request);
        $clientId = (string) $request->get('client_id');

        McpAuthorization::assertClientAccess($user, $clientId);

        $query = Article::query()
            ->where('client_id', $clientId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->integer('per_page', self::DEFAULT_PER_PAGE)));

        /** @var LengthAwarePaginator<int, Article> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        if ($paginator->total() === 0) {
            throw new McpToolException('No articles found.');
        }

        $articlesData = collect($paginator->items())
            ->map(fn (Article $article) => $article->toMcpStructuredData())
            ->values()
            ->toArray();

        $count = $paginator->count();
        $total = $paginator->total();
        $message = 'Showing '.$count.' '.Str::plural('article', $count)
            .' (page '.$paginator->currentPage().' of '.$paginator->lastPage().', '.$total.' '.Str::plural('article', $total).' total):';

        return McpResponse::textWithStructured(
            $message,
            $articlesData,
            [
                'articles' => $articlesData,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()
                ->description('The ULID of the client to list articles for')
                ->required(),
            'page' => $schema->integer()
                ->description('Page number (1-based, default: 1)')
                ->min(1),
            'per_page' => $schema->integer()
                ->description('Number of articles per page (default: '.self::DEFAULT_PER_PAGE.', max: '.self::MAX_PER_PAGE.')')
                ->min(1)
                ->max(self::MAX_PER_PAGE),
        ];
    }
}
