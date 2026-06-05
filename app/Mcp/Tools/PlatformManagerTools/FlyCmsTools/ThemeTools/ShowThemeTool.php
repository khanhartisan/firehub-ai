<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show a FlyCMS theme by ID for the platform linked to the given channel.')]
class ShowThemeTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $themeId = (string) $request->get('theme_id');
        $flycms = $this->getFlyCmsManager($channel, $user);

        if (! $theme = $flycms->showTheme($themeId)) {
            throw new McpToolException("Theme [{$themeId}] not found.");
        }

        return McpResponse::details('Theme', $theme->toMcpStructuredData());
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
            'theme_id' => $schema->string()
                ->description('The FlyCMS theme ID to show')
                ->required(),
        ];
    }
}
