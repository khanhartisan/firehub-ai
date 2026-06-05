<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update the FlyCMS website linked to the given channel.')]
class UpdateWebsiteTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        if (!$flycmsWebsiteId = $channel->reference) {
            throw new McpToolException('Channel '.$channel->id.' does not have a FlyCMS website reference.');
        }

        $flycms = $this->getFlyCmsManager($channel, $user);

        $updatePayload = $request->get('update_website_data');

        if (! is_array($updatePayload) || $updatePayload === []) {
            throw new McpToolException('Provide at least one field in update_website_data.');
        }

        try {
            $updateWebsiteData = (new UpdateWebsiteData)->setData($updatePayload);
            $websiteData = $flycms->updateWebsite($flycmsWebsiteId, $updateWebsiteData);

            return McpResponse::updated('Website', $websiteData->toMcpStructuredData());
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
            'update_website_data' => $schema->object(new UpdateWebsiteData()->toJsonSchema($schema))
                ->required()
                ->description('Website update payload'),
        ];
    }
}
