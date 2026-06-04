<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
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

#[Description('List FlyCMS tags for the website linked to the given channel.')]
class ListTagsTool extends FlyCmsTool
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

        $pagination = $this->resolvePagination($request);

        $tagFilter = null;
        if (is_array($filterPayload = $request->get('tag_filter')) && $filterPayload !== []) {
            $tagFilter = (new TagFilter)->setFilterData($filterPayload);
        }

        $tags = $this->getFlyCmsManager($channel)->listTags(
                $this->requireFlyCmsWebsiteId($channel),
                $pagination->page,
                $pagination->perPage,
                $tagFilter
            );

        if ($tags === []) {
            throw new McpToolException('No tags found.');
        }

        $tagsData = array_map(
            static fn ($tag) => $tag->toMcpStructuredData(),
            $tags
        );

        $count = count($tagsData);
        $message = 'Found '.$count.' '.Str::plural('tag', $count)
            .$pagination->listMessageSuffix().':';

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
            ...$this->paginationSchemaProperties($schema, 'tags'),
            'tag_filter' => $schema->object(new TagFilter()->toJsonSchema($schema))
                ->description('Optional filters when listing tags'),
        ];
    }
}
