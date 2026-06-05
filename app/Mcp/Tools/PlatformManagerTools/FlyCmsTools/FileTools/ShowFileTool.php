<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show a FlyCMS file by ID for the platform linked to the given channel.')]
class ShowFileTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $fileId = (string) $request->get('file_id');
        $fileData = $this->resolveFileForChannel($channel, $user, $fileId);

        return McpResponse::details('File', $fileData->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'file_id' => $schema->string()
                ->description('The FlyCMS file ID to show')
                ->required(),
        ];
    }
}
