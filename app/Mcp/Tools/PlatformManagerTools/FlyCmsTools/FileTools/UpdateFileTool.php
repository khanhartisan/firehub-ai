<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\UpdateFileData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update a FlyCMS file on the platform linked to the given channel.')]
class UpdateFileTool extends FlyCmsTool
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
        $updatePayload = $request->get('update_file_data');

        if (! is_array($updatePayload) || $updatePayload === []) {
            throw new McpToolException('Provide at least one field in update_file_data.');
        }

        $this->resolveFileForChannel($channel, $user, $fileId);

        try {
            $updateFileData = (new UpdateFileData)->setData($updatePayload);
            $fileData = $this->getFlyCmsManager($channel, $user)->updateFile($fileId, $updateFileData);

            return McpResponse::updated('file', $fileData->toMcpStructuredData());
        } catch (FlyCmsException|InvalidArgumentException $e) {
            throw new McpToolException($e->getMessage(), previous: $e);
        }
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
                ->description('The FlyCMS file ID to update')
                ->required(),
            'update_file_data' => $schema->object(new UpdateFileData()->toJsonSchema($schema))
                ->required()
                ->description('File update payload'),
        ];
    }
}
