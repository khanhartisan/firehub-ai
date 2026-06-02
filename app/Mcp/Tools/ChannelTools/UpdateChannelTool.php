<?php

namespace App\Mcp\Tools\ChannelTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing channel.')]
class UpdateChannelTool extends Tool
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
            'channel_id' => ['required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_id' => ['sometimes', 'required', 'string'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);

        if (
            ! $request->exists('name')
            && ! $request->exists('client_id')
            && ! $request->exists('config')
        ) {
            throw new McpToolException('Provide at least one field to update (name, client_id, or config).');
        }

        $user = McpAccess::user($request);

        $channel = McpAccess::channel($user, (string) $request->get('channel_id'));

        if ($request->exists('client_id')) {
            $client = McpAccess::client($user, (string) $request->get('client_id'));
            $channel->client()->associate($client);
        }

        if ($request->exists('name')) {
            $channel->name = (string) $request->get('name');
        }

        if ($request->exists('config')) {
            $config = $request->get('config');
            $channel->config = is_array($config) ? $config : null;
        }

        DB::transaction(function () use ($channel): void {
            $channel->save();
        });

        $channel->refresh();

        return McpResponse::updated('channel', $channel->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel to update')
                ->required(),
            'name' => $schema->string()
                ->description('Channel display name'),
            'client_id' => $schema->string()
                ->description('The ULID of the client that owns the channel'),
            'config' => $schema
                ->object()
                ->nullable()
                ->description('Channel-specific configuration, use '.(new GetChannelConfigSchemaTool()->name()).' to see the schema.'),
        ];
    }
}
