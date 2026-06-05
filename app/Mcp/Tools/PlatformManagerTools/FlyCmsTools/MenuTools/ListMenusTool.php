<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List FlyCMS menus for the website linked to the given channel.')]
class ListMenusTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $menus = $this->getFlyCmsManager($channel, $user)->listMenus($this->requireFlyCmsWebsiteId($channel));

        if (!$menus) {
            throw new McpToolException('No menus found.');
        }

        $count = count($menus);
        $message = 'Found '.$count.' '.Str::plural('menu', $count).':';

        return McpResponse::list(
            'menu',
            array_map(
                static fn ($menu) => $menu->toMcpStructuredData(),
                $menus
            ),
            'menus',
            $message
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
        ];
    }
}
