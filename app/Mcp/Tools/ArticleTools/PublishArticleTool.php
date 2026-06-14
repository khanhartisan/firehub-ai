<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Enums\ArticleStatus;
use App\Enums\PublicationStatus;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use App\Models\Publication;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Publish an article to one or more channels by creating publications.')]
class PublishArticleTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'article_id' => ['required', 'string'],
            'channel_ids' => ['required', 'array', 'min:1'],
            'channel_ids.*' => ['required', 'string', 'distinct'],
        ]);

        $user = McpAccess::user($request);
        $clientId = (string) $request->get('client_id');
        $article = McpAccess::article($user, $clientId, (string) $request->get('article_id'));

        /** @var list<string> $channelIds */
        $channelIds = array_values(array_map(
            strval(...),
            $request->get('channel_ids'),
        ));

        $channels = collect($channelIds)
            ->map(fn (string $channelId) => McpAccess::channel($user, $channelId));

        $this->assertChannelsBelongToClient($channels, $clientId);

        $defaultStatus = $article->status === ArticleStatus::READY
            ? PublicationStatus::PENDING
            : PublicationStatus::AWAITING;

        $changed = false;

        $publications = DB::transaction(function () use ($channels, $article, $defaultStatus, &$changed): Collection {
            return $channels->map(function ($channel) use ($article, $defaultStatus, &$changed): Publication {
                $publication = Publication::query()->firstOrNew([
                    'channel_id' => $channel->id,
                    'publishable_type' => $article->getMorphClass(),
                    'publishable_id' => $article->id,
                ]);

                if (! $publication->exists) {
                    $publication->title = $article->title;
                    $publication->description = $article->excerpt;
                    $publication->status = $defaultStatus;
                    $publication->save();
                    $changed = true;

                    return $publication;
                }

                if (in_array($publication->status, PublicationStatus::retriableStatuses(), true)) {
                    $publication->status = PublicationStatus::AWAITING;
                    $publication->attempts = 0;
                    $publication->save();
                    $changed = true;
                }

                return $publication;
            });
        });

        $items = $publications
            ->map(fn (Publication $publication) => $publication->fresh()->toMcpStructuredData())
            ->values()
            ->all();

        return McpResponse::list(
            'publication',
            $items,
            'publications',
            $changed ? 'Successfully created publications:' : 'All publications already exist:',
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()
                ->description('The ULID of the client that owns the article')
                ->required(),
            'article_id' => $schema->string()
                ->description('The ULID of the article to publish')
                ->required(),
            'channel_ids' => $schema->array()
                ->items($schema->string())
                ->description('ULIDs of the channels to publish the article to')
                ->required(),
        ];
    }

    /**
     * @param  Collection<int, \App\Models\Channel>  $channels
     */
    private function assertChannelsBelongToClient(Collection $channels, string $clientId): void
    {
        $invalidChannelIds = $channels
            ->filter(fn ($channel) => $channel->client_id !== $clientId)
            ->pluck('id')
            ->all();

        if ($invalidChannelIds !== []) {
            throw new McpToolException(
                'Channel(s) do not belong to this client: '.implode(', ', $invalidChannelIds).'.'
            );
        }
    }
}
