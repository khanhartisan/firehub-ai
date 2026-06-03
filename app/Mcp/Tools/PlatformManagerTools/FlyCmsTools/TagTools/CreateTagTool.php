<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a FlyCMS tag on the website linked to the given channel.')]
class CreateTagTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $flycmsWebsiteId = $this->requireFlyCmsWebsiteId($channel);
        $createPayload = $request->get('create_tag_data');

        if (! is_array($createPayload) || $createPayload === []) {
            throw new McpToolException('Provide create_tag_data with at least name and slug.');
        }

        $flycms = $this->getFlyCmsManager($channel);

        try {
            $createPayload['website_id'] = $flycmsWebsiteId;
            $createTagData = (new CreateTagData)->setData($createPayload);
            $tagData = $flycms->createTag($createTagData);

            return McpResponse::created('tag', $tagData->toMcpStructuredData());
        } catch (FlyCmsException $e) {
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
        $properties = (new CreateTagData)->toJsonSchema($schema);
        unset($properties['website_id']);

        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'create_tag_data' => $schema->object($properties)
                ->required()
                ->description('Tag creation payload (website_id is set from the channel reference)'),
        ];
    }
}
