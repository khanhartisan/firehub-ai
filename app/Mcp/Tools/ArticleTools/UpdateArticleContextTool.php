<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Contracts\CommonData\AudienceContext;
use App\Contracts\Model\Article\Context as ArticleContext;
use App\Models\Article;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
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
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory|Response
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

        if (! $this->hasContextFieldToUpdate($request)) {
            return Response::error('Provide at least one context field to update.');
        }

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

        $context = $article->context instanceof ArticleContext
            ? $article->context->clone()
            : new ArticleContext;

        $this->applyContextUpdates($context, $request);
        $article->context = $context;

        DB::transaction(function () use ($article): void {
            $article->save();
        });

        $article->refresh();

        $data = $article->toMcpDetailStructuredData();

        return Response::make(Response::text('Successfully updated the article context:'."\n\n".json_encode($data)))
            ->withStructuredContent($data);
    }

    /**
     * Get the tool's input schema.
     *
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

    private function hasContextFieldToUpdate(Request $request): bool
    {
        foreach (self::CONTEXT_FIELDS as $field) {
            if ($request->exists($field)) {
                return true;
            }
        }

        return false;
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
            $context->setAudienceContexts($this->hydrateAudienceContexts($request->get('audience_contexts')));
        }
    }

    /**
     * @return AudienceContext[]
     */
    private function hydrateAudienceContexts(mixed $rawAudienceContexts): array
    {
        if (! is_array($rawAudienceContexts)) {
            return [];
        }

        $audienceContexts = [];

        foreach ($rawAudienceContexts as $row) {
            if (! is_array($row) || $row === []) {
                continue;
            }

            $audienceContext = new AudienceContext;

            foreach ($row as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                $description = $audienceContext->getDescription($key) ?? ('Audience context field: '.$key);
                $audienceContext->set($key, $description, $value);
            }

            $audienceContexts[] = $audienceContext;
        }

        return $audienceContexts;
    }
}
