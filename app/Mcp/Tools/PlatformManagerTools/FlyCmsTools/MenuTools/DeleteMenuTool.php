<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a FlyCMS menu on the website linked to the given channel.')]
class DeleteMenuTool extends FlyCmsTool
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

        $this->resolveMenuForChannel($flycms, $flycmsWebsiteId, $menuId);

        if (! $flycms->deleteMenu($menuId)) {
            throw new McpToolException("Menu [{$menuId}] not found.");
        }

        return McpResponse::textWithStructured(
            "Successfully deleted menu [{$menuId}].",
            ['menu_id' => $menuId],
            ['menu_id' => $menuId],
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
            'menu_id' => $schema->string()
                ->description('The FlyCMS menu ID to delete')
                ->required(),
        ];
    }
}
