<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a FlyCMS menu on the website linked to the given channel.')]
class CreateMenuTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $createPayload = $request->get('create_menu_data');

        if (! is_array($createPayload) || $createPayload === []) {
            throw new McpToolException('Provide create_menu_data with at least key.');
        }

        try {
            $createPayload['website_id'] = $this->requireFlyCmsWebsiteId($channel);
            $createMenuData = (new CreateMenuData)->setData($createPayload);
            $menuData = $this->getFlyCmsManager($channel)->createMenu($createMenuData);

            return McpResponse::created('menu', $menuData->toMcpStructuredData());
        } catch (FlyCmsException $e) {
            throw new McpToolException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = (new CreateMenuData)->toJsonSchema($schema);
        unset($properties['website_id']);

        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'create_menu_data' => $schema->object($properties)
                ->required()
                ->description('Menu creation payload (website_id is set from the channel reference)'),
        ];
    }
}
