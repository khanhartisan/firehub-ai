<?php

namespace App\Mcp\Tools\ClientTools;

use App\Contracts\Model\Client\Context;
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
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
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

        if (! McpRequest::hasAnyField($request, self::CONTEXT_FIELDS)) {
            throw new McpToolException('Provide at least one context field to update.');
        }

        $user = McpAuthorization::user($request);
        $client = McpAuthorization::client($user, (string) $request->get('client_id'));

        $context = $client->context instanceof Context
            ? $client->context->clone()
            : new Context;

        $this->applyContextUpdates($context, $request);
        $client->context = $context;

        DB::transaction(function () use ($client): void {
            $client->save();
        });

        $client->refresh();

        return McpResponse::updated('client context', $client->toMcpStructuredData());
    }

    /**
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
            $context->setAudienceContexts(AudienceContextHydrator::fromArray($request->get('audience_contexts')));
        }
    }
}
