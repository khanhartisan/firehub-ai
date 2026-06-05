<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools;

use App\Contracts\PlatformManager\FlyCms\Filters\ThemeFilter;
use App\Mcp\Concerns\ResolvesMcpPagination;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List FlyCMS themes available on the platform linked to the given channel.')]
class ListThemesTool extends FlyCmsTool
{
    use ResolvesMcpPagination;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $flycms = $this->getFlyCmsManager($channel, $user);

        $pagination = $this->resolvePagination($request);

        $filterPayload = is_array($request->get('theme_filter')) ? $request->get('theme_filter') : [];
        $themeFilter = (new ThemeFilter)->setFilterData($filterPayload);

        $themes = $flycms->listThemes($pagination->page, $pagination->perPage, $themeFilter);

        if ($themes === []) {
            throw new McpToolException('No themes found.');
        }

        $themesData = array_map(
            static fn ($theme) => $theme->toMcpStructuredData(),
            $themes
        );

        $count = count($themesData);
        $message = 'Found '.$count.' '.Str::plural('theme', $count)
            .$pagination->listMessageSuffix().':';

        return McpResponse::list('theme', $themesData, 'themes', $message);
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
            ...$this->paginationSchemaProperties($schema, 'themes'),
            'theme_filter' => $schema->object((new ThemeFilter)->toJsonSchema($schema))
                ->description('Optional filters when listing themes'),
        ];
    }
}
