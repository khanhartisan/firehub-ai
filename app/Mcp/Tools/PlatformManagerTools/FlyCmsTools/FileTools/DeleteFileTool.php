<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a FlyCMS file on the platform linked to the given channel.')]
class DeleteFileTool extends FlyCmsTool
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
        $this->resolveFileForChannel($channel, $user, $fileId);

        try {
            $this->getFlyCmsManager($channel, $user)->deleteFile($fileId);
        } catch (FlyCmsException|InvalidArgumentException $e) {
            throw new McpToolException($e->getMessage(), previous: $e);
        }

        return McpResponse::textWithStructured(
            "Successfully deleted file [{$fileId}].",
            ['file_id' => $fileId],
            ['file_id' => $fileId],
        );
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
                ->description('The FlyCMS file ID to delete')
                ->required(),
        ];
    }
}
