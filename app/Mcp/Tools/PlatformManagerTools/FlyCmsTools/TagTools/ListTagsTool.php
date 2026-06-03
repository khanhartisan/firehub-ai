<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List FlyCMS tags for the website linked to the given channel.')]
class ListTagsTool extends FlyCmsTool
{
    private const int DEFAULT_LIMIT = 100;

    private const int MAX_LIMIT = 100;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $flycmsWebsiteId = $this->requireFlyCmsWebsiteId($channel);
        $flycms = $this->getFlyCmsManager($channel);

        $page = max(1, (int) $request->integer('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, (int) $request->integer('limit', self::DEFAULT_LIMIT)));

        $tagFilter = null;
        if (is_array($filterPayload = $request->get('tag_filter')) && $filterPayload !== []) {
            $tagFilter = (new TagFilter)->setFilterData($filterPayload);
        }

        $tags = $flycms->listTags($flycmsWebsiteId, $page, $limit, $tagFilter);

        if ($tags === []) {
            throw new McpToolException('No tags found.');
        }

        $tagsData = array_map(
            static fn ($tag) => $tag->toMcpStructuredData(),
            $tags
        );

        $count = count($tagsData);
        $message = 'Found '.$count.' '.Str::plural('tag', $count)
            .' (page '.$page.', limit '.$limit.'):';

        return McpResponse::list('tag', $tagsData, 'tags', $message);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'page' => $schema->integer()
                ->description('Page number (1-based, default: 1)')
                ->min(1),
            'limit' => $schema->integer()
                ->description('Maximum tags per page (default: '.self::DEFAULT_LIMIT.', max: '.self::MAX_LIMIT.')')
                ->min(1)
                ->max(self::MAX_LIMIT),
            'tag_filter' => $schema->object(new TagFilter()->toJsonSchema($schema))
                ->description('Optional filters when listing tags'),
        ];
    }
}
