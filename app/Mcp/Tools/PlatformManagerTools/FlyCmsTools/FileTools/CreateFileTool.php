<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Upload a file to FlyCMS for the platform linked to the given channel.')]
class CreateFileTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $createPayload = $request->get('create_file_data');

        if (! is_array($createPayload) || $createPayload === []) {
            throw new McpToolException('Provide create_file_data with at least ext.');
        }

        $fileData = (string) $request->get('file_data');
        $content = base64_decode($fileData, true);

        if ($content === false) {
            throw new McpToolException('file_data must be valid base64-encoded content.');
        }

        try {
            $createFileData = (new CreateFileData)->setData($createPayload);
            $fileResource = $this->getFlyCmsManager($channel, $user)->createFile($content, $createFileData);

            return McpResponse::created('file', $fileResource->toMcpStructuredData());
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
            'file_data' => $schema->string()
                ->description('Base64-encoded file binary content')
                ->required(),
            'create_file_data' => $schema->object(new CreateFileData()->toJsonSchema($schema))
                ->required()
                ->description('File creation metadata'),
        ];
    }
}
