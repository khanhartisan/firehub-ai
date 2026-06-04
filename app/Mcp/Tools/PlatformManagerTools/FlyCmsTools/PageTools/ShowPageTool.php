<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show a FlyCMS page by ID for the website linked to the given channel.')]
class ShowPageTool extends FlyCmsTool
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
        $pageData = $this->resolvePageForChannel($channel, $pageId);

        return McpResponse::details('Page', $pageData->toMcpStructuredData());
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
                ->description('The FlyCMS page ID to show')
                ->required(),
        ];
    }
}
