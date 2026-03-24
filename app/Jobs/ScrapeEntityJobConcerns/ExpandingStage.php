<?php

namespace App\Jobs\ScrapeEntityJobConcerns;

use App\Contracts\PageParser\PageData;
use App\Enums\EntityType;
use App\Models\Entity;
use App\Models\Source;
use App\Utils\EntityUrlNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait ExpandingStage
{
    protected function expand(Entity $entity): void
    {
        if (env('APP_DEBUG')) {
            dump('Expanding, entity '.$entity->id);
        }

        if ($entity->type !== EntityType::PAGE
            or ! $snapshot = $entity->currentSnapshot
        ) {
            return;
        }

        $pageDataFilePath = $this->getFilePathForPageData($snapshot);
        if (! $pageDataJson = Storage::get($pageDataFilePath)) {
            return;
        }

        $pageData = PageData::fromJson($pageDataJson);
        $linkedUrls = $pageData->getLinkedPageUrls();

        $this->createLinkedEntitiesAndQueueScrapes($entity, $linkedUrls);
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
                Entity::query()->create([
                    'source_id' => $source->id,
                    'url' => $url,
                ]);
            }
        });
    }

    protected function normalizeLinkedUrl(string $url): string
    {
        $url = EntityUrlNormalizer::normalize($url);
        if ($url === '' || ! preg_match('#\Ahttps?://#i', $url)) {
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
}
