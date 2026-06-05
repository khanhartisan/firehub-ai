<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
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

#[Description('List FlyCMS files for the platform linked to the given channel.')]
class ListFilesTool extends FlyCmsTool
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

        $filterPayload = is_array($request->get('file_filter')) ? $request->get('file_filter') : [];
        $filterPayload['user_id'] = $this->getFlyCmsUserId($channel, $user);

        $fileFilter = (new FileFilter)->setFilterData($filterPayload);

        $orderDirection = $request->has('order_direction')
            ? (int) $request->integer('order_direction')
            : null;

        $files = $this->getFlyCmsManager($channel, $user)->listFiles(
            $pagination->page,
            $pagination->perPage,
            $orderDirection,
            $fileFilter
        );

        if ($files === []) {
            throw new McpToolException('No files found.');
        }

        $filesData = array_map(
            static fn ($file) => $file->toMcpStructuredData(),
            $files
        );

        $count = count($filesData);
        $message = 'Found '.$count.' '.Str::plural('file', $count)
            .$pagination->listMessageSuffix().':';

        return McpResponse::list('file', $filesData, 'files', $message);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = (new FileFilter)->toJsonSchema($schema);
        unset($properties['user_id']);

        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            ...$this->paginationSchemaProperties($schema, 'files'),
            'order_direction' => $schema->integer()
                ->description('Sort by created_at: -1 = newer first, 1 = older first, omit for default order')
                ->enum([-1, 1]),
            'file_filter' => $schema->object($properties)
                ->description('Optional filters when listing files (scoped to the request user)'),
        ];
    }
}
