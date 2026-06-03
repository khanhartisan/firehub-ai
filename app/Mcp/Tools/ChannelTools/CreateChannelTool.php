<?php

namespace App\Mcp\Tools\ChannelTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use App\Models\Channel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a new publishing channel for a client on a platform.')]
class CreateChannelTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        if ($request->has('name')) {
            $request->merge(['name' => trim((string) $request->get('name'))]);
        }

        $request->validate([
            'client_id' => ['required', 'string'],
            'platform_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);

        $user = McpAccess::user($request);
        $client = McpAccess::client($user, (string) $request->get('client_id'));
        $platform = McpAccess::platform((string) $request->get('platform_id'));

        $channel = new Channel;
        $channel->client()->associate($client);
        $channel->platform()->associate($platform);
        $channel->name = (string) $request->get('name');

        if ($request->exists('config')) {
            $config = $request->get('config');
            $channel->config = is_array($config) ? $config : null;
        }

        DB::transaction(function () use ($channel): void {
            $channel->save();
        });

        $channel->refresh();

        return McpResponse::created('channel', $channel->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()
                ->description('The ULID of the client that owns the channel')
                ->required(),
            'platform_id' => $schema->string()
                ->description('The ULID of the platform to publish to')
                ->required(),
            'name' => $schema->string()
                ->description('Channel display name')
                ->required(),
            'config' => $schema
                ->object()
                ->nullable()
                ->description('Channel-specific configuration, use '.(new GetChannelConfigSchemaTool()->name()).' to see the schema, omit if the schema is null or empty.'),
        ];
    }
}
