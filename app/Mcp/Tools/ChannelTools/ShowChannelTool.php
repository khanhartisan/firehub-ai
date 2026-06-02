<?php

namespace App\Mcp\Tools\ChannelTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show details of an existing channel.')]
class ShowChannelTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'channel_id' => ['required', 'string'],
        ]);

        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, (string) $request->get('channel_id'));

        return McpResponse::details('Channel', $channel->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel to show')
                ->required(),
        ];
    }
}
