<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use App\Models\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a website in the FlyCms platform representing for the given channel.')]
class CreateWebsiteTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $flycms = $this->getFlyCmsManager($channel);

        // Check if the website exists
        if ($flycmsWebsiteId = $channel->reference
            and $websiteData = $flycms->showWebsite($flycmsWebsiteId)
        ) {
            return McpResponse::details('Website', $websiteData->toMcpStructuredData());
        }

        // Create the website
        try {
            $createWebsiteData = (new CreateWebsiteData)->setData($request->get('create_website_data'));
            $websiteData = $flycms->createWebsite($createWebsiteData);
            $channel->reference = $websiteData->get('id');

            DB::transaction(fn () => $channel->save());

            return McpResponse::details('Website', $websiteData->toMcpStructuredData());
        } catch (FlyCmsException $e) {
            throw new McpToolException($e->getMessage());
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
            'create_website_data' => $schema->object(new CreateWebsiteData()->toJsonSchema($schema))
                ->required()
                ->description('Website creation payload'),
        ];
    }
}
