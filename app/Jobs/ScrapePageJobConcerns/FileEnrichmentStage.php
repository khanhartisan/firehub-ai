<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Enums\ScrapingStatus;
use App\Jobs\EmbeddingJob;
use App\Jobs\ScrapeFileJob;
use App\Models\File;
use App\Models\Fileable;
use App\Models\Page;
use App\Models\Snapshot;
use App\Utils\Debugger;
use App\Utils\Str;
use App\Utils\UrlNormalizer;

trait FileEnrichmentStage
{
    protected function handleFileEnrichmentStage(Page $page): ?bool
    {
        Debugger::devConsoleDump('Handling file enrichment stage, page '.$page->id);

        /** @var Snapshot $snapshot */
        if (!$snapshot = $page->currentSnapshot) {
            return false;
        }

        $pageData = $snapshot->getPageData();
        $markdown = $pageData->getMarkdownContent();

        // Extract file urls and create a file record foreach
        $fileUrls = array_unique(Str::extractFileUrls($markdown));
        $fileUrls = array_slice($fileUrls, 0, 100);
        if ($fileUrls) {
            $fileMap = [];
            foreach ($fileUrls as $fileUrl) {
                $fileUrlNormalized = UrlNormalizer::normalize($fileUrl);
                $fileUrlHash = File::getUrlHash($fileUrlNormalized);
                if (!$file = File::query()->where('url_hash', $fileUrlHash)->first()) {
                    $file = new File();
                    $file->url = $fileUrlNormalized;
                    $file->save();
                }

                // Ensure corresponding fileable record exists
                Fileable::query()->firstOrCreate([
                    'fileable_type' => $snapshot->getMorphClass(),
                    'fileable_id' => $snapshot->getKey(),
                    'file_id' => $file->id
                ]);

                $fileMap[$file->id] = $fileUrl;
            }

            // Update markdown content
            foreach ($fileMap as $fileId => $fileUrl) {
                $markdown = str_replace($fileUrl, 'file://'.$fileId, $markdown);
            }
        }

        // If it has scraping files, we wait
        if ($scrapingFiles = $snapshot
            ->files()
            ->whereNotIn('scraping_status', [
                ScrapingStatus::SUCCESS, ScrapingStatus::FAILED,
            ])
            ->get()
            and !$scrapingFiles->isEmpty()
        ) {
            $scrapingFiles->each(fn (File $file) => ScrapeFileJob::dispatch($file));
            return null;
        }

        // If it has files awaiting embedding, we wait
        if ($embeddingFiles = $snapshot
            ->files()
            ->where('is_embeddable', true)
            ->where('is_embedded', false)
            ->get()
            and !$embeddingFiles->isEmpty()
        ) {
            $embeddingFiles->each(fn (File $file) => EmbeddingJob::dispatch($file));
            return null;
        }

        return true;
    }
}