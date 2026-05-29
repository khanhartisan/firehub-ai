<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Author\AuthorContexts\CognitiveContext;
use App\Contracts\Model\Author\AuthorContexts\ConstraintContext;
use App\Contracts\Model\Author\AuthorContexts\DemographicContext;
use App\Contracts\Model\Author\AuthorContexts\ExperientialContext;
use App\Contracts\Model\Author\AuthorContexts\LinguisticContext;
use App\Models\Author;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new author for a client.')]
class CreateAuthorTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        if ($request->has('name')) {
            $request->merge(['name' => trim((string) $request->get('name'))]);
        }

        $request->validate([
            'client_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'cognitive_context' => ['sometimes', 'array'],
            'constraint_context' => ['sometimes', 'array'],
            'demographic_context' => ['sometimes', 'array'],
            'experiential_context' => ['sometimes', 'array'],
            'linguistic_context' => ['sometimes', 'array'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        /** @var Client|null $client */
        $client = $user->clients()->where('clients.id', $request->get('client_id'))->first();

        if ($client === null) {
            return Response::error('Client not found or you do not have access to this client.');
        }

        $author = new Author;
        $author->client()->associate($client);
        $author->name = (string) $request->get('name');

        $context = new AuthorContext;
        $this->applyContextUpdates($context, $request);
        $author->context = $context;

        DB::transaction(function () use ($author): void {
            $author->save();
        });

        $author->refresh();

        $data = $author->toMcpStructuredData();

        return Response::make(Response::text('Successfully created a new author:'."\n\n".json_encode($data)))
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
                ->description('The ULID of the client to create the author for')
                ->required(),
            'name' => $schema->string()
                ->description('Author display name')
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
            $subContext = $this->hydrateSubContext(CognitiveContext::class, $request->get('cognitive_context'));
            if ($subContext instanceof CognitiveContext) {
                $context->setCognitiveContext($subContext);
            }
        }

        if ($request->exists('constraint_context')) {
            $subContext = $this->hydrateSubContext(ConstraintContext::class, $request->get('constraint_context'));
            if ($subContext instanceof ConstraintContext) {
                $context->setConstraintContext($subContext);
            }
        }

        if ($request->exists('demographic_context')) {
            $subContext = $this->hydrateSubContext(DemographicContext::class, $request->get('demographic_context'));
            if ($subContext instanceof DemographicContext) {
                $context->setDemographicContext($subContext);
            }
        }

        if ($request->exists('experiential_context')) {
            $subContext = $this->hydrateSubContext(ExperientialContext::class, $request->get('experiential_context'));
            if ($subContext instanceof ExperientialContext) {
                $context->setExperientialContext($subContext);
            }
        }

        if ($request->exists('linguistic_context')) {
            $subContext = $this->hydrateSubContext(LinguisticContext::class, $request->get('linguistic_context'));
            if ($subContext instanceof LinguisticContext) {
                $context->setLinguisticContext($subContext);
            }
        }
    }

    /**
     * @param  class-string<SemanticContext>  $class
     */
    private function hydrateSubContext(string $class, mixed $raw): ?SemanticContext
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        /** @var SemanticContext $context */
        $context = new $class;
        $template = $context->withEmptyFields(recursive: true, clone: false);

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
