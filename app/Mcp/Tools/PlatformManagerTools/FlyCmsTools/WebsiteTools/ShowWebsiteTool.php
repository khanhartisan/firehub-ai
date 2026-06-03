<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show the FlyCMS website linked to the given channel.')]
class ShowWebsiteTool extends FlyCmsTool
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

        $flycms = $this->getFlyCmsManager($channel);

        if (! $websiteData = $flycms->showWebsite($flycmsWebsiteId)) {
            throw new McpToolException("Website [{$flycmsWebsiteId}] not found.");
        }

        return McpResponse::details('Website', $websiteData->toMcpStructuredData());
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
        ];
    }
}
