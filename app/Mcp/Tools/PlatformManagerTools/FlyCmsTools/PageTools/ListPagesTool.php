<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

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

        $pages = $flycms->listPages($flycmsWebsiteId, $page, $limit);

        if ($pages === []) {
            throw new McpToolException('No pages found.');
        }

        $pagesData = array_map(
            static fn ($pageResource) => $pageResource->toMcpStructuredData(),
            $pages
        );

        $count = count($pagesData);
        $message = 'Found '.$count.' '.Str::plural('page', $count)
            .' (page '.$page.', limit '.$limit.'):';

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
            'page' => $schema->integer()
                ->description('Page number (1-based, default: 1)')
                ->min(1),
            'limit' => $schema->integer()
                ->description('Maximum pages per page (default: '.self::DEFAULT_LIMIT.', max: '.self::MAX_LIMIT.')')
                ->min(1)
                ->max(self::MAX_LIMIT),
        ];
    }
}
