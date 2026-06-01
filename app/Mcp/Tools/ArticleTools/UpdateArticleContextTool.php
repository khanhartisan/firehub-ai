<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Contracts\Model\Article\Context as ArticleContext;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\AudienceContextHydrator;
use App\Mcp\Support\McpAuthorization;
use App\Mcp\Support\McpRequest;
use App\Mcp\Support\McpResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update the semantic context of an existing article.')]
class UpdateArticleContextTool extends Tool
{
    /**
     * @var list<string>
     */
    private const CONTEXT_FIELDS = [
        'tone_of_voice',
        'guidelines',
        'idea_guidelines',
        'audience_contexts',
        'meta',
    ];

    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'article_id' => ['required', 'string'],
            'tone_of_voice' => ['sometimes', 'string'],
            'guidelines' => ['sometimes', 'array'],
            'guidelines.*' => ['string'],
            'idea_guidelines' => ['sometimes', 'array'],
            'idea_guidelines.*' => ['string'],
            'audience_contexts' => ['sometimes', 'array'],
            'audience_contexts.*' => ['array'],
            'meta' => ['sometimes', 'array'],
        ]);

        if (! McpRequest::hasAnyField($request, self::CONTEXT_FIELDS)) {
            throw new McpToolException('Provide at least one context field to update.');
        }

        $user = McpAuthorization::user($request);
        $article = McpAuthorization::article(
            $user,
            (string) $request->get('client_id'),
            (string) $request->get('article_id'),
        );

        $context = $article->context instanceof ArticleContext
            ? $article->context->clone()
            : new ArticleContext;

        $this->applyContextUpdates($context, $request);
        $article->context = $context;

        DB::transaction(function () use ($article): void {
            $article->save();
        });

        $article->refresh();

        return McpResponse::updated('article context', $article->toMcpDetailStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = [
            'client_id' => $schema->string()
                ->description('The ULID of the client that owns the article')
                ->required(),
            'article_id' => $schema->string()
                ->description('The ULID of the article whose context to update')
                ->required(),
        ];

        foreach ((new ArticleContext)->toJsonSchema($schema) as $key => $fieldSchema) {
            $fields[$key] = $fieldSchema;
        }

        return $fields;
    }

    private function applyContextUpdates(ArticleContext $context, Request $request): void
    {
        if ($request->exists('tone_of_voice')) {
            $context->setToneOfVoice((string) $request->get('tone_of_voice'));
        }

        if ($request->exists('guidelines')) {
            $guidelines = $request->get('guidelines');
            $context->setGuidelines(is_array($guidelines) ? $guidelines : []);
        }

        if ($request->exists('idea_guidelines')) {
            $ideaGuidelines = $request->get('idea_guidelines');
            $context->setIdeaGuidelines(is_array($ideaGuidelines) ? $ideaGuidelines : []);
        }

        if ($request->exists('meta')) {
            $meta = $request->get('meta');
            $context->setMeta(is_array($meta) ? $meta : []);
        }

        if ($request->exists('audience_contexts')) {
            $context->setAudienceContexts(AudienceContextHydrator::fromArray($request->get('audience_contexts')));
        }
    }
}
