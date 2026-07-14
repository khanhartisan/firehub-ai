<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Enums\ArticleStatus;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpRequest;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update an existing article. Use update-article-context-tool for semantic context fields.')]
class UpdateArticleTool extends Tool
{
    /**
     * @var list<string>
     */
    private const UPDATABLE_FIELDS = [
        'status',
        'language',
        'temporal',
    ];

    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'article_id' => ['required', 'string'],
            'status' => ['sometimes', 'required', Rule::enum(ArticleStatus::class)],
            'language' => ['sometimes', 'nullable', Rule::enum(Language::class)],
            'temporal' => ['sometimes', 'nullable', Rule::enum(Temporal::class)],
        ]);

        if (! McpRequest::hasAnyField($request, self::UPDATABLE_FIELDS)) {
            throw new McpToolException(
                'Provide at least one field to update (status, language, or temporal).'
            );
        }

        $user = McpAccess::user($request);
        $article = McpAccess::article(
            $user,
            (string) $request->get('client_id'),
            (string) $request->get('article_id'),
        );

        if ($request->exists('status')) {
            $article->status = $request->get('status');
        }

        if ($request->exists('language')) {
            $article->language = $request->get('language');
        }

        if ($request->exists('temporal')) {
            $article->temporal = $request->get('temporal');
        }

        DB::transaction(function () use ($article): void {
            $article->save();
        });

        $article->refresh();

        return McpResponse::updated('article', $article->toMcpStructuredData());
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
                ->description('The ULID of the article to update')
                ->required(),
            'status' => $schema
                ->integer()
                ->description(
                    'Article status. '
                    .collect(ArticleStatus::cases())
                        ->map(fn (ArticleStatus $status) => $status->value.': '.$status->name.' ('.ArticleStatus::describe($status).')')
                        ->join(', ')
                ),
            'language' => $schema
                ->string()
                ->enum(Language::class)
                ->nullable()
                ->description('Article language (BCP 47 tag)'),
            'temporal' => $schema
                ->string()
                ->enum(Temporal::class)
                ->nullable()
                ->description('Article temporal classification'),
        ];
    }
}
