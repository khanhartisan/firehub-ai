<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

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

#[Description('List FlyCMS pages for the website linked to the given channel.')]
class ListPagesTool extends FlyCmsTool
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

        $flycmsWebsiteId = $this->requireFlyCmsWebsiteId($channel);
        $flycms = $this->getFlyCmsManager($channel, $user);

        $pagination = $this->resolvePagination($request);

        $pages = $flycms->listPages($flycmsWebsiteId, $pagination->page, $pagination->perPage);

        if ($pages === []) {
            throw new McpToolException('No pages found.');
        }

        $pagesData = array_map(
            static fn ($pageResource) => $pageResource->toMcpStructuredData(),
            $pages
        );

        $count = count($pagesData);
        $message = 'Found '.$count.' '.Str::plural('page', $count)
            .$pagination->listMessageSuffix().':';

        return McpResponse::list('page', $pagesData, 'pages', $message);
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
            ...$this->paginationSchemaProperties($schema, 'pages'),
        ];
    }
}
