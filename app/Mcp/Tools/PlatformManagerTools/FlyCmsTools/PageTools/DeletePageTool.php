<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a FlyCMS page on the website linked to the given channel.')]
class DeletePageTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $pageId = (string) $request->get('page_id');
        $this->resolvePageForChannel($channel, $user, $pageId);

        try {
            $this->getFlyCmsManager($channel, $user)->deletePage($pageId);
        } catch (InvalidArgumentException $e) {
            throw new McpToolException($e->getMessage(), previous: $e);
        }

        return McpResponse::textWithStructured(
            "Successfully deleted page [{$pageId}].",
            ['page_id' => $pageId],
            ['page_id' => $pageId],
        );
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
                ->description('The FlyCMS page ID to delete')
                ->required(),
        ];
    }
}
