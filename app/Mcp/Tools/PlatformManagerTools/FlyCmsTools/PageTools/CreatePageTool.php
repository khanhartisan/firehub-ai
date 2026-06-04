<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\CreatePageData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a FlyCMS page on the website linked to the given channel.')]
class CreatePageTool extends FlyCmsTool
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
        $createPayload = $request->get('create_page_data');

        if (! is_array($createPayload) || $createPayload === []) {
            throw new McpToolException('Provide create_page_data with at least slug and title.');
        }

        $flycms = $this->getFlyCmsManager($channel);

        try {
            $createPayload['website_id'] = $flycmsWebsiteId;
            $createPageData = (new CreatePageData)->setData($createPayload);
            $pageData = $flycms->createPage($createPageData);

            return McpResponse::created('page', $pageData->toMcpStructuredData());
        } catch (FlyCmsException $e) {
            throw new McpToolException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = (new CreatePageData)->toJsonSchema($schema);
        unset($properties['website_id']);

        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'create_page_data' => $schema->object($properties)
                ->required()
                ->description('Page creation payload (website_id is set from the channel reference)'),
        ];
    }
}
