<?php

namespace App\Mcp\Tools\ChannelTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Get the channel config schema for a platform.')]
class GetChannelConfigSchemaTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'platform_id' => ['required', 'string'],
        ]);

        $platform = McpAccess::platform((string) $request->get('platform_id'));
        $channelConfig = $platform->getPlatformManager()->makeChannelConfig();
        $schema = $channelConfig?->toJsonSchema(new JsonSchemaTypeFactory()) ?? [];

        return McpResponse::details('Channel config schema', [
            'platform_id' => $platform->id,
            'platform_type' => $platform->type->value,
            'channel_config_schema' => $schema,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'platform_id' => $schema
                ->string()
                ->description('The ULID of the platform to inspect.')
                ->required(),
        ];
    }
}
