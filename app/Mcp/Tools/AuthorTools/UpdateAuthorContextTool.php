<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Author\AuthorContexts\CognitiveContext;
use App\Contracts\Model\Author\AuthorContexts\ConstraintContext;
use App\Contracts\Model\Author\AuthorContexts\DemographicContext;
use App\Contracts\Model\Author\AuthorContexts\ExperientialContext;
use App\Contracts\Model\Author\AuthorContexts\LinguisticContext;
use App\Mcp\Exceptions\McpToolException;
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

#[Description('Update the persona context of an existing author.')]
class UpdateAuthorContextTool extends Tool
{
    /**
     * @var list<string>
     */
    private const CONTEXT_FIELDS = [
        'cognitive_context',
        'constraint_context',
        'demographic_context',
        'experiential_context',
        'linguistic_context',
    ];

    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'author_id' => ['required', 'string'],
            'cognitive_context' => ['sometimes', 'array'],
            'constraint_context' => ['sometimes', 'array'],
            'demographic_context' => ['sometimes', 'array'],
            'experiential_context' => ['sometimes', 'array'],
            'linguistic_context' => ['sometimes', 'array'],
        ]);

        if (! McpRequest::hasAnyField($request, self::CONTEXT_FIELDS)) {
            throw new McpToolException('Provide at least one context field to update.');
        }

        $user = McpAuthorization::user($request);
        $author = McpAuthorization::author($user, (string) $request->get('author_id'));

        $context = $author->context instanceof AuthorContext
            ? $author->context->clone()
            : new AuthorContext;

        $this->applyContextUpdates($context, $request);
        $author->context = $context;

        DB::transaction(function () use ($author): void {
            $author->save();
        });

        $author->refresh();

        return McpResponse::updated('author context', $author->toMcpStructuredData());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = [
            'author_id' => $schema->string()
                ->description('The ULID of the author whose context to update')
                ->required(),
        ];

        foreach ((new AuthorContext)->toJsonSchema($schema) as $key => $fieldSchema) {
            $fields[$key] = $fieldSchema;
        }

        return $fields;
    }

    private function applyContextUpdates(AuthorContext $context, Request $request): void
    {
        if ($request->exists('cognitive_context')) {
            $subContext = $this->mergeSubContext(
                CognitiveContext::class,
                $context,
                'cognitive_context',
                $request->get('cognitive_context')
            );
            if ($subContext instanceof CognitiveContext) {
                $context->setCognitiveContext($subContext);
            }
        }

        if ($request->exists('constraint_context')) {
            $subContext = $this->mergeSubContext(
                ConstraintContext::class,
                $context,
                'constraint_context',
                $request->get('constraint_context')
            );
            if ($subContext instanceof ConstraintContext) {
                $context->setConstraintContext($subContext);
            }
        }

        if ($request->exists('demographic_context')) {
            $subContext = $this->mergeSubContext(
                DemographicContext::class,
                $context,
                'demographic_context',
                $request->get('demographic_context')
            );
            if ($subContext instanceof DemographicContext) {
                $context->setDemographicContext($subContext);
            }
        }

        if ($request->exists('experiential_context')) {
            $subContext = $this->mergeSubContext(
                ExperientialContext::class,
                $context,
                'experiential_context',
                $request->get('experiential_context')
            );
            if ($subContext instanceof ExperientialContext) {
                $context->setExperientialContext($subContext);
            }
        }

        if ($request->exists('linguistic_context')) {
            $subContext = $this->mergeSubContext(
                LinguisticContext::class,
                $context,
                'linguistic_context',
                $request->get('linguistic_context')
            );
            if ($subContext instanceof LinguisticContext) {
                $context->setLinguisticContext($subContext);
            }
        }
    }

    /**
     * @param  class-string<SemanticContext>  $class
     */
    private function mergeSubContext(string $class, AuthorContext $authorContext, string $fieldKey, mixed $raw): ?SemanticContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $existingArray = $authorContext->toArray()[$fieldKey]['value'] ?? null;

        /** @var SemanticContext $context */
        $context = is_array($existingArray)
            ? $class::fromArray($existingArray)
            : new $class;

        $template = (new $class)->withEmptyFields(recursive: true, clone: false);

        foreach ($raw as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $description = $template->getDescription($key) ?? ('Author context field: '.$key);
            $context->set($key, $description, $value);
        }

        return $context;
    }
}
