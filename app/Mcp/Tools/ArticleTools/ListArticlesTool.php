<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Mcp\Concerns\ResolvesMcpPagination;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use App\Models\Article;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List articles that belong to the current user\'s client, with pagination.')]
class ListArticlesTool extends Tool
{
    use ResolvesMcpPagination;

    protected function defaultListLimit(): int
    {
        return 15;
    }

    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$this->maxListLimit()],
        ]);

        $user = McpAccess::user($request);
        $clientId = (string) $request->get('client_id');

        McpAccess::assertClientAccess($user, $clientId);

        $query = Article::query()
            ->where('client_id', $clientId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $pagination = $this->resolvePagination($request);

        /** @var LengthAwarePaginator<int, Article> $paginator */
        $paginator = $query->paginate($pagination->perPage, ['*'], 'page', $pagination->page);

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
                    'per_page' => $pagination->perPage,
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
            ...$this->paginationSchemaProperties($schema, 'articles'),
        ];
    }
}
