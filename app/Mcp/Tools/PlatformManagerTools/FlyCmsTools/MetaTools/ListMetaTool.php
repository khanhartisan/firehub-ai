<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MetaTools;

use App\Contracts\PlatformManager\FlyCms\Filters\MetaFilter;
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

#[Description('List FlyCMS meta entries for the website linked to the given channel.')]
class ListMetaTool extends FlyCmsTool
{
    use ResolvesMcpPagination;

    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $pagination = $this->resolvePagination($request);

        $metaFilter = null;
        if (is_array($filterPayload = $request->get('meta_filter')) && $filterPayload !== []) {
            $metaFilter = (new MetaFilter)->setFilterData($filterPayload);
        }

        $meta = $this->getFlyCmsManager($channel, $user)->listMeta(
            'website',
            $this->requireFlyCmsWebsiteId($channel),
            $pagination->page,
            $pagination->perPage,
            $metaFilter
        );

        if ($meta === []) {
            throw new McpToolException('No meta entries found.');
        }

        $metaData = array_map(
            static fn ($entry) => $entry->toMcpStructuredData(),
            $meta
        );

        $count = count($metaData);
        $message = 'Found '.$count.' meta '.Str::plural('entry', $count)
            .$pagination->listMessageSuffix().':';

        return McpResponse::list('meta entry', $metaData, 'meta', $message);
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
            ...$this->paginationSchemaProperties($schema, 'meta entries'),
            'meta_filter' => $schema->object(new MetaFilter()->toJsonSchema($schema))
                ->description('Optional filters when listing meta entries'),
        ];
    }
}
