<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show a FlyCMS menu by ID for the website linked to the given channel.')]
class ShowMenuTool extends FlyCmsTool
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
        $menuId = (string) $request->get('menu_id');
        $flycms = $this->getFlyCmsManager($channel);
        $menuData = $this->resolveMenuForChannel($flycms, $flycmsWebsiteId, $menuId);

        return McpResponse::details('Menu', $menuData->toMcpStructuredData());
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
            'menu_id' => $schema->string()
                ->description('The FlyCMS menu ID to show')
                ->required(),
        ];
    }
}
