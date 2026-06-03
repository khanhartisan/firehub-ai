<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show a FlyCMS tag by ID for the website linked to the given channel.')]
class ShowTagTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        if (! $flycmsWebsiteId = $channel->reference) {
            throw new McpToolException('Channel '.$channel->id.' does not have a FlyCMS website reference.');
        }

        $tagId = (string) $request->get('tag_id');
        $flycms = $this->getFlyCmsManager($channel);

        if (! $tagData = $flycms->showTag($tagId)) {
            throw new McpToolException("Tag [{$tagId}] not found.");
        }

        if (($tagData->get('website_id') ?? null) !== $flycmsWebsiteId) {
            throw new McpToolException("Tag [{$tagId}] not found.");
        }

        return McpResponse::details('Tag', $tagData->toMcpStructuredData());
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
                ->description('The FlyCMS tag ID to show')
                ->required(),
        ];
    }
}
