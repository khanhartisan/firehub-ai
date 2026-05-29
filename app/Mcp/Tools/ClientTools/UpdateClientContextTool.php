<?php

namespace App\Mcp\Tools\ClientTools;

use App\Contracts\CommonData\AudienceContext;
use App\Contracts\Model\Client\Context;
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

#[Description('Update the editorial context of an existing client.')]
class UpdateClientContextTool extends Tool
{
    /**
     * @var list<string>
     */
    private const CONTEXT_FIELDS = [
        'name',
        'description',
        'tone_of_voice',
        'industry',
        'niches',
        'core_mission',
        'guidelines',
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
            'name' => ['sometimes', 'string'],
            'description' => ['sometimes', 'string'],
            'tone_of_voice' => ['sometimes', 'string'],
            'industry' => ['sometimes', 'string'],
            'niches' => ['sometimes', 'array'],
            'niches.*' => ['string'],
            'core_mission' => ['sometimes', 'string'],
            'guidelines' => ['sometimes', 'array'],
            'guidelines.*' => ['string'],
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

        /** @var Client|null $client */
        $client = $user->clients()->where('clients.id', $request->get('client_id'))->first();

        if ($client === null) {
            return Response::error('Client not found or you do not have access to this client.');
        }

        $context = $client->context instanceof Context
            ? $client->context->clone()
            : new Context;

        $this->applyContextUpdates($context, $request);
        $client->context = $context;

        DB::transaction(function () use ($client): void {
            $client->save();
        });

        $client->refresh();

        $data = $client->toMcpStructuredData();
        return Response::make(Response::text('Successfully updated the client context:'."\n\n".json_encode($data)))
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
                ->description('The ULID of the client whose context to update')
                ->required(),
        ];

        foreach ((new Context)->toJsonSchema($schema) as $key => $fieldSchema) {
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

    private function applyContextUpdates(Context $context, Request $request): void
    {
        if ($request->exists('name')) {
            $context->setName((string) $request->get('name'));
        }

        if ($request->exists('description')) {
            $context->setDescription((string) $request->get('description'));
        }

        if ($request->exists('tone_of_voice')) {
            $context->setToneOfVoice((string) $request->get('tone_of_voice'));
        }

        if ($request->exists('industry')) {
            $context->setIndustry((string) $request->get('industry'));
        }

        if ($request->exists('niches')) {
            $niches = $request->get('niches');
            $context->setNiches(is_array($niches) ? $niches : []);
        }

        if ($request->exists('core_mission')) {
            $context->setCoreMission((string) $request->get('core_mission'));
        }

        if ($request->exists('guidelines')) {
            $guidelines = $request->get('guidelines');
            $context->setGuidelines(is_array($guidelines) ? $guidelines : []);
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
            if (! is_array($row)) {
                continue;
            }

            if ($row === []) {
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
