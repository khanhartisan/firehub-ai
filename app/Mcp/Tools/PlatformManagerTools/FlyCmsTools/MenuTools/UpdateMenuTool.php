<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update a FlyCMS menu on the website linked to the given channel.')]
class UpdateMenuTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $menuId = (string) $request->get('menu_id');
        $updatePayload = $request->get('update_menu_data');

        if (! is_array($updatePayload) || $updatePayload === []) {
            throw new McpToolException('Provide at least one field in update_menu_data.');
        }

        $this->resolveMenuForChannel($channel, $user, $menuId);

        try {
            $updateMenuData = (new UpdateMenuData)->setData($updatePayload);
            $menuData = $this->getFlyCmsManager($channel, $user)->updateMenu($menuId, $updateMenuData);

            return McpResponse::updated('menu', $menuData->toMcpStructuredData());
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
            'menu_id' => $schema->string()
                ->description('The FlyCMS menu ID to update')
                ->required(),
            'update_menu_data' => $schema->object(new UpdateMenuData()->toJsonSchema($schema))
                ->required()
                ->description('Menu update payload'),
        ];
    }
}
