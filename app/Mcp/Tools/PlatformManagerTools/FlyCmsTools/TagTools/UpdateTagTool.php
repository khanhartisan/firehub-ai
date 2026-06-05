<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update a FlyCMS tag on the website linked to the given channel.')]
class UpdateTagTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $tagId = (string) $request->get('tag_id');
        $updatePayload = $request->get('update_tag_data');

        if (! is_array($updatePayload) || $updatePayload === []) {
            throw new McpToolException('Provide at least one field in update_tag_data.');
        }

        $this->resolveTagForChannel($channel, $user, $tagId);

        try {
            $updateTagData = (new UpdateTagData)->setData($updatePayload);
            $tagData = $this->getFlyCmsManager($channel, $user)->updateTag($tagId, $updateTagData);

            return McpResponse::updated('tag', $tagData->toMcpStructuredData());
        } catch (FlyCmsException|InvalidArgumentException $e) {
            throw new McpToolException($e->getMessage(), previous: $e);
        }
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'tag_id' => $schema->string()
                ->description('The FlyCMS tag ID to update')
                ->required(),
            'update_tag_data' => $schema->object(new UpdateTagData()->toJsonSchema($schema))
                ->required()
                ->description('Tag update payload'),
        ];
    }
}
