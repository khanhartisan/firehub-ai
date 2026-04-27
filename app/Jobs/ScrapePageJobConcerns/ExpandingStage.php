<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Contracts\PageParser\PageData;
use App\Enums\ScrapableType;
use App\Models\Page;
use App\Models\Source;
use App\Utils\Debugger;
use App\Utils\UrlNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait ExpandingStage
{
    protected function handleExpandingStage(Page $page): void
    {
        Debugger::devConsoleDump('Expanding, page '.$page->id);

        // Skip if the source isn't auto scheduled
        if (!$page->source?->schedule_scraping) {
            return;
        }

        if ($page->type !== ScrapableType::TEXT
            or !$snapshot = $page->currentSnapshot
        ) {
            return;
        }

        $pageData = $this->getPageDataForSnapshot($snapshot);
        $linkedUrls = $pageData->getLinkedPageUrls();

        $this->createLinkedPagesAndQueueScrapes($page, $linkedUrls);
    }

    /**
     * Create entities for linked URLs on the same host (inline so they are not lost if a job never runs).
     * Scrape jobs for new entities are dispatched by the scheduler job when they become due.
     *
     * @param  array<int, string>  $linkedUrls
     */
    protected function createLinkedPagesAndQueueScrapes(Page $page, array $linkedUrls): void
    {
        if (empty($linkedUrls)) {
            return;
        }

        $pageHost = parse_url($page->url, PHP_URL_HOST);
        if ($pageHost === null || $pageHost === '') {
            return;
        }

        $sameHostUrls = array_values(array_filter($linkedUrls, function (string $url) use ($pageHost): bool {
            $host = parse_url($url, PHP_URL_HOST);

            return $host !== null && $host !== '' && strtolower($host) === strtolower($pageHost);
        }));

        if (empty($sameHostUrls)) {
            return;
        }

        $source = $page->source ?? Source::find($page->source_id);
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
        $existingHashes = Page::query()
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
                Page::query()->create([
                    'source_id' => $source->id,
                    'url' => $url,
                ]);
            }
        });
    }

    protected function normalizeLinkedUrl(string $url): string
    {
        $url = UrlNormalizer::normalize($url);
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
