<?php

namespace App\Jobs;

use App\Contracts\VerticalResolver\Vertical as ContractVertical;
use App\Enums\EntityType;
use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStatus;
use App\Facades\PageClassifier;
use App\Facades\PageParser;
use App\Facades\ScrapePolicyEngine;
use App\Facades\VerticalResolver as VerticalResolverFacade;
use App\Facades\Scraper;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Source;
use App\Models\Vertical as VerticalModel;
use App\Models\Tag;
use App\Utils\HtmlCleaner;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

class ScrapeEntityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Delete the job if the entity no longer exists.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Maximum number of times to attempt the job.
     */
    public int $tries = 2;

    /**
     * Number of seconds to wait before retrying after a failure.
     */
    public int $backoff = 300;

    /**
     * The entity to scrape.
     */
    public Entity $entity;

    /**
     * Create a new job instance.
     */
    public function __construct(Entity $entity)
    {
        $this->entity = $entity->withoutRelations();
        $this->onQueue(QueueEnum::SCRAPING->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $entity = $this->entity;

        if ($entity->scraping_status !== ScrapingStatus::QUEUED) {
            Log::debug("ScrapeEntityJob: Entity [{$entity->id}] status is {$entity->scraping_status->name}, skipping");
            return;
        }

        $entity->scraping_status = ScrapingStatus::FETCHING;
        DB::transaction(fn () => $entity->save());

        $fetchStartedAt = microtime(true);

        try {
            $response = $this->fetchUrl($entity->url);
            $statusCode = $response->getStatusCode();
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);

            if ($statusCode >= 400) {
                $this->markEntityFailed($entity, $statusCode, null, $fetchDurationMs, "HTTP {$statusCode}");
                return;
            }

            $html = (string) $response->getBody();

            $linkedUrls = $this->processFetchedContent($entity, $html, $fetchDurationMs);

            $this->createLinkedEntitiesAndQueueScrapes($entity, $linkedUrls);
        } catch (ConnectException $e) {
            Log::warning("ScrapeEntityJob: Connect error for entity [{$entity->id}]: {$e->getMessage()}");
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);
            $this->markEntityFailed($entity, null, ScrapingStatus::TIMEOUT, $fetchDurationMs, $e->getMessage());
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;

            Log::warning("ScrapeEntityJob: Request error for entity [{$entity->id}]: {$e->getMessage()}");

            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);

            $responseBodySnippet = '';
            $response = $e->getResponse();
            if ($response !== null && $this->isResponseBodyReadable($response)) {
                $body = $response->getBody();
                if ($body->isReadable()) {
                    $body->rewind();
                    $responseBodySnippet = $body->read(10_000);
                }
            }

            $this->markEntityFailed(
                $entity,
                $statusCode,
                null,
                $fetchDurationMs,
                $e->getMessage()."\n".$responseBodySnippet
            );
        } catch (\Throwable $e) {
            Log::error("ScrapeEntityJob: Unexpected error for entity [{$entity->id}]: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);
            $errorLogs = $e->getMessage() . "\n" . $e->getTraceAsString();
            $this->markEntityFailed($entity, null, ScrapingStatus::FAILED, $fetchDurationMs, $errorLogs);
        }
    }

    /**
     * Fetch URL and return response. Override in tests if needed.
     */
    protected function fetchUrl(string $url): ResponseInterface
    {
        return Scraper::fetch($url);
    }

    /**
     * Process fetched HTML: classify, parse, create snapshot, run policy, update entity.
     *
     * @return array<int, string> Linked page URLs from the parser (for discovery).
     */
    protected function processFetchedContent(Entity $entity, string $html, int $fetchDurationMs): array
    {
        $cleanedHtml = HtmlCleaner::clean($html);

        // Long-running AI/service calls outside any transaction.
        $classification = PageClassifier::classify($cleanedHtml);
        $pageData = PageParser::parse($cleanedHtml);

        // Resolve and propose verticals based on content and existing / proposed Vertical models.
        $verticalMatches = [];
        $didResolveVerticals = false;
        $proposalVerticalIds = [];

        $verticalContent = $pageData->getMarkdownContent() !== ''
            ? $pageData->getMarkdownContent()
            : $cleanedHtml;

        // 1) Always call propose() first (even when there are no verticals yet),
        // create any proposed Vertical models, and associate them to the source.
        try {
            $initialVerticalModels = VerticalModel::all();

            $initialContractVerticals = $initialVerticalModels
                ->map(function (VerticalModel $model): ContractVertical {
                    $vertical = new ContractVertical($model->name, $model->description);
                    $vertical->setIdentifier((string) $model->id);

                    return $vertical;
                })
                ->all();

            $verticalProposals = VerticalResolverFacade::propose($verticalContent, $initialContractVerticals);

            if (! empty($verticalProposals)) {
                foreach ($verticalProposals as $proposal) {
                    $this->persistProposedVerticalTree($proposal, null, $entity->source_id, $proposalVerticalIds);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ScrapeEntityJob: Failed to propose verticals for entity', [
                'entity_id' => $entity->id,
                'exception' => $e,
            ]);
        }

        // 2) Re-load all verticals (including newly proposed ones) and call resolve()
        // to get matches that will be attached to the entity. If there are no verticals
        // at all, there is nothing to match, so resolve() is skipped.
        $allVerticalModels = VerticalModel::all();

        if ($allVerticalModels->isNotEmpty()) {
            try {
                $contractVerticals = $allVerticalModels
                    ->map(function (VerticalModel $model): ContractVertical {
                        $vertical = new ContractVertical($model->name, $model->description);
                        $vertical->setIdentifier((string) $model->id);

                        return $vertical;
                    })
                    ->all();

                $verticalMatches = VerticalResolverFacade::resolve($verticalContent, $contractVerticals);
                $didResolveVerticals = true;
            } catch (\Throwable $e) {
                Log::warning('ScrapeEntityJob: Failed to resolve verticals for entity', [
                    'entity_id' => $entity->id,
                    'exception' => $e,
                ]);
            }
        }

        $linkedUrls = $pageData->getLinkedPageUrls();

        $contentLength = strlen($pageData->getMarkdownContent());
        $linkCount = count($linkedUrls);
        if ($linkCount === 0 && $pageData->getMarkdownContent() !== '') {
            $linkCount = $this->countLinksInMarkdown($pageData->getMarkdownContent());
        }
        $mediaCount = $this->countMediaInMarkdown($pageData->getMarkdownContent());

        $version = $entity->snapshots_count + 1;
        $filePath = 'snapshots/'.$entity->id.'/'.($snapshotId = Str::ulid()).'.html';
        Storage::put($filePath, $html);
        $fileSize = strlen($html);

        DB::transaction(function () use ($snapshotId, $entity, $classification, $pageData, $contentLength, $linkCount, $mediaCount, $fetchDurationMs, $version, $filePath, $fileSize, $verticalMatches, $didResolveVerticals, $proposalVerticalIds, $allVerticalModels) {
            // Snapshot with SUCCESS status for history/evaluation (failure paths create snapshots in markEntityFailed).
            $snapshot = new Snapshot([
                'id' => $snapshotId,
                'entity_id' => $entity->id,
                'scraping_status' => ScrapingStatus::SUCCESS,
                'version' => $version,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_mime_type' => 'text/html',
                'file_extension' => 'html',
                'content_length' => $contentLength,
                'link_count' => $linkCount,
                'media_count' => $mediaCount,
                'structured_data_count' => 0,
                'fetch_duration_ms' => $fetchDurationMs,
            ]);
            $snapshot->save();

            $entity->type = EntityType::PAGE;
            $entity->page_type = $classification->getPageType();
            $entity->content_type = $classification->getContentType();
            $entity->temporal = $classification->getTemporal();
            $entity->description = $pageData->getExcerpt();
            if (strlen((string) $entity->description) > 1024) {
                $entity->description = substr((string) $entity->description, 0, 1021) . '...';
            }
            $entity->source_published_at = $pageData->getPublishedAt();
            $entity->source_updated_at = $pageData->getUpdatedAt();
            $entity->canonical_number = $pageData->getCanonicalNumber() ?? 0;
            $entity->fetched_at = Carbon::now();
            $entity->snapshots_count = $version;
            $entity->save();

            $tagIds = collect($classification->getTags())
                ->map(fn (string $name): string
                    => Tag::query()
                        ->firstOrCreate(['name' => $name])
                        ->id
                )
                ->all();
            $entityTagSync = $entity->tags()->sync($tagIds);
            // TODO: Update entity count on relation sync

            // Map resolved verticals to database Vertical models and attach to the entity.
            // Proposed verticals are created and attached to the source above; whether they
            // are attached to the entity is decided solely by resolve(). This is best-effort
            // and should not cause the scrape to fail.
            try {
                if ($didResolveVerticals) {
                    $verticalIds = [];

                    $modelsByIdentifier = $allVerticalModels->keyBy(fn (VerticalModel $model): string => (string) $model->id);
                    $parentByIdentifier = $allVerticalModels->mapWithKeys(function (VerticalModel $model): array {
                        return [(string) $model->id => $model->parent_id ? (string) $model->parent_id : null];
                    })->all();

                    foreach ($verticalMatches as $match) {
                        $identifier = $match->getVerticalIdentifier();
                        if (! $modelsByIdentifier->has($identifier)) {
                            continue;
                        }

                        // Attach the matched vertical and all ancestors so nesting queries work.
                        $current = (string) $modelsByIdentifier->get($identifier)->id;
                        $visited = [];
                        while ($current !== '' && ! isset($visited[$current])) {
                            $visited[$current] = true;
                            $verticalIds[] = $current;
                            $current = (string) ($parentByIdentifier[$current] ?? '');
                        }
                    }

                    $verticalIds = array_values(array_unique($verticalIds));

                    // Sync to reflect latest resolution. If no matches, this clears previous verticals.
                    $entity->verticals()->sync($verticalIds);
                }
            } catch (\Throwable $e) {
                Log::warning('ScrapeEntityJob: Failed to sync verticals for entity', [
                    'entity_id' => $entity->id,
                    'exception' => $e,
                ]);
            }
        });

        // Long-running policy evaluation outside transaction.
        $entity->refresh();
        $policyResult = ScrapePolicyEngine::evaluate($entity);

        DB::transaction(function () use ($entity, $policyResult) {
            $entity->next_scrape_at = $policyResult->getNextScrapeAt();
            $entity->policy_result = $policyResult->toArray();
            $entity->scraping_status = ScrapingStatus::SUCCESS;
            $entity->attempts = 0;
            $entity->save();
        });

        return $linkedUrls;
    }

    /**
     * Create entities for linked URLs on the same host (inline so they are not lost if a job never runs).
     * Scrape jobs for new entities are dispatched by the scheduler job when they become due.
     *
     * @param  array<int, string>  $linkedUrls
     */
    protected function createLinkedEntitiesAndQueueScrapes(Entity $entity, array $linkedUrls): void
    {
        if (empty($linkedUrls)) {
            return;
        }

        $entityHost = parse_url($entity->url, PHP_URL_HOST);
        if ($entityHost === null || $entityHost === '') {
            return;
        }

        $sameHostUrls = array_values(array_filter($linkedUrls, function (string $url) use ($entityHost): bool {
            $host = parse_url($url, PHP_URL_HOST);

            return $host !== null && $host !== '' && strtolower($host) === strtolower($entityHost);
        }));

        if (empty($sameHostUrls)) {
            return;
        }

        $source = $entity->source ?? Source::find($entity->source_id);
        if (! $source instanceof Source) {
            return;
        }

        $normalized = [];
        foreach ($sameHostUrls as $url) {
            $url = $this->normalizeLinkedUrl($url);
            if ($url !== '') {
                $normalized[$url] = true;
            }
        }
        $normalizedUrls = array_keys($normalized);
        if (empty($normalizedUrls)) {
            return;
        }

        $hashes = array_map('sha1', $normalizedUrls);
        $existingHashes = Entity::query()
            ->where('source_id', $source->id)
            ->whereIn('url_hash', $hashes)
            ->pluck('url_hash')
            ->flip()
            ->all();

        $newUrls = [];
        foreach ($normalizedUrls as $url) {
            if (! isset($existingHashes[sha1($url)])) {
                $newUrls[] = $url;
            }
        }

        if (empty($newUrls)) {
            return;
        }

        DB::transaction(function () use ($source, $newUrls): void {
            foreach ($newUrls as $url) {
                Entity::create([
                    'source_id' => $source->id,
                    'url' => $url,
                ]);
            }
        });
    }

    protected function normalizeLinkedUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return '';
        }

        return $url;
    }

    protected function countLinksInMarkdown(string $markdown): int
    {
        return (int) preg_match_all('/]\s*\([^)]+\)/', $markdown);
    }

    protected function countMediaInMarkdown(string $markdown): int
    {
        $img = (int) preg_match_all('/!\[[^]]*]\s*\([^)]+\)/', $markdown);
        $embeds = (int) preg_match_all('/<img\s/i', $markdown);

        return $img + $embeds;
    }

    /**
     * Mark entity as failed, create a snapshot with the appropriate status for history/evaluation,
     * apply backoff or stop if max attempts reached.
     */
    protected function markEntityFailed(Entity $entity, ?int $statusCode, ?ScrapingStatus $status = null, ?int $fetchDurationMs = null, ?string $errorLogs = null): void
    {
        $status = $status ?? ($this->isBlockedStatus($statusCode) ? ScrapingStatus::BLOCKED : ScrapingStatus::FAILED);
        $maxAttempts = config('queue.max_scrape_attempts');

        $entity->increment('attempts');
        $entity->refresh();

        DB::transaction(function () use ($entity, $status, $fetchDurationMs, $errorLogs, $maxAttempts) {
            $version = $entity->snapshots_count + 1;
            // Always create a snapshot with the failure status so it can be used as history for evaluating entities.
            $snapshot = new Snapshot([
                'entity_id' => $entity->id,
                'scraping_status' => $status,
                'version' => $version,
                'fetch_duration_ms' => $fetchDurationMs,
                'error_logs' => $errorLogs,
            ]);
            $snapshot->save();

            if ($entity->attempts >= $maxAttempts) {
                $entity->update([
                    'scraping_status' => $status,
                    'next_scrape_at' => null,
                    'snapshots_count' => $version,
                ]);
            } else {
                $delaySeconds = $this->backoffSecondsForAttempt($entity->attempts);
                $entity->update([
                    'scraping_status' => $status,
                    'next_scrape_at' => Carbon::now()->addSeconds($delaySeconds),
                    'snapshots_count' => $version,
                ]);
            }
        });

        if ($entity->attempts >= $maxAttempts) {
            Log::warning("ScrapeEntityJob: Entity [{$entity->id}] exceeded max attempts ({$entity->attempts}/{$maxAttempts}), stopping.");
        }
    }

    /**
     * Exponential backoff in seconds: base * 2^(attempt-1), capped at 7 days.
     */
    protected function backoffSecondsForAttempt(int $attempt): int
    {
        $baseSeconds = 3600;   // 1 hour
        $maxSeconds = 86400 * 7; // 7 days
        $delay = $baseSeconds * (2 ** ($attempt - 1));

        return (int) min($delay, $maxSeconds);
    }

    protected function isBlockedStatus(?int $statusCode): bool
    {
        return $statusCode === 403 || $statusCode === 429;
    }

    /**
     * Whether the response body is human-readable (text-like) and safe to include in error logs.
     */
    private function isResponseBodyReadable(ResponseInterface $response): bool
    {
        $contentTypes = $response->getHeader('Content-Type');
        $contentType = (string) ($contentTypes[0] ?? '');
        $contentType = strtolower(trim(explode(';', $contentType)[0]));

        if ($contentType === '') {
            return false;
        }

        $readableApplicationTypes = [
            'application/json',
            'application/xml',
            'application/javascript',
            'application/xhtml+xml',
        ];

        return str_starts_with($contentType, 'text/')
            || in_array($contentType, $readableApplicationTypes, true);
    }

    /**
     * Persist a proposed vertical tree (name, description, children) into VerticalModel with correct parent_id.
     *
     * @param  array<string, string>  $proposalVerticalIds  IDs of all created/updated verticals (passed by reference)
     */
    protected function persistProposedVerticalTree(ContractVertical $node, ?VerticalModel $parentModel, ?string $sourceId, array &$proposalVerticalIds): void
    {
        $parentId = $parentModel?->id;

        $model = VerticalModel::query()->firstOrCreate(
            ['name' => $node->getName()],
            [
                'description' => $node->getDescription(),
                'parent_id' => $parentId,
            ]
        );

        if ($model->parent_id !== $parentId) {
            $model->update(['parent_id' => $parentId, 'description' => $node->getDescription()]);
        }

        $proposalVerticalIds[] = $model->id;

        if ($sourceId !== null) {
            $model->sources()->syncWithoutDetaching([$sourceId]);
        }

        foreach ($node->getChildren() as $child) {
            $this->persistProposedVerticalTree($child, $model, $sourceId, $proposalVerticalIds);
        }
    }
}
