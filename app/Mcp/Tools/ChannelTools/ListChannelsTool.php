<?php

namespace App\Mcp\Tools\ChannelTools;

use App\Mcp\Concerns\ResolvesMcpPagination;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use App\Models\Channel;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show the list of channels that belong to the current user\'s clients.')]
class ListChannelsTool extends Tool
{
    use ResolvesMcpPagination;

    protected function defaultListLimit(): int
    {
        return 15;
    }

    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['sometimes', 'string'],
            'platform_id' => ['sometimes', 'string'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$this->maxListLimit()],
        ]);

        $user = McpAccess::user($request);

        $query = Channel::query()
            ->whereIn('client_id', $user->clients()->select('clients.id'))
            ->orderBy('name');

        if ($request->exists('client_id')) {
            $clientId = (string) $request->get('client_id');
            McpAccess::assertClientAccess($user, $clientId);
            $query->where('client_id', $clientId);
        }

        if ($request->exists('platform_id')) {
            $platformId = (string) $request->get('platform_id');
            McpAccess::platform($platformId);
            $query->where('platform_id', $platformId);
        }

        $pagination = $this->resolvePagination($request);

        /** @var LengthAwarePaginator<int, Channel> $paginator */
        $paginator = $query->paginate($pagination->perPage, ['*'], 'page', $pagination->page);

        if ($paginator->total() === 0) {
            throw new McpToolException('No channels found.');
        }

        $channelsData = collect($paginator->items())
            ->map(fn (Channel $channel) => $channel->toMcpStructuredData())
            ->values()
            ->toArray();

        $count = $paginator->count();
        $total = $paginator->total();
        $message = 'Showing '.$count.' '.Str::plural('channel', $count)
            .' (page '.$paginator->currentPage().' of '.$paginator->lastPage().', '.$total.' '.Str::plural('channel', $total).' total):';

        return McpResponse::textWithStructured(
            $message,
            $channelsData,
            [
                'channels' => $channelsData,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $pagination->perPage,
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()
                ->description('Optional ULID to filter channels by client'),
            'platform_id' => $schema->string()
                ->description('Optional ULID to filter channels by platform'),
            ...$this->paginationSchemaProperties($schema, 'channels'),
        ];
    }
}
