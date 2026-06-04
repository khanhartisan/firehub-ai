<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\UpdatePageData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update a FlyCMS page on the website linked to the given channel.')]
class UpdatePageTool extends FlyCmsTool
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
        $pageId = (string) $request->get('page_id');
        $updatePayload = $request->get('update_page_data');

        if (! is_array($updatePayload) || $updatePayload === []) {
            throw new McpToolException('Provide at least one field in update_page_data.');
        }

        $flycms = $this->getFlyCmsManager($channel);
        $this->resolvePageForChannel($flycms, $flycmsWebsiteId, $pageId);

        try {
            $updatePageData = (new UpdatePageData)->setData($updatePayload);
            $pageData = $flycms->updatePage($pageId, $updatePageData);

            return McpResponse::updated('page', $pageData->toMcpStructuredData());
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
            'page_id' => $schema->string()
                ->description('The FlyCMS page ID to update')
                ->required(),
            'update_page_data' => $schema->object(new UpdatePageData()->toJsonSchema($schema))
                ->required()
                ->description('Page update payload'),
        ];
    }
}
