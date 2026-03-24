<?php

namespace App\Jobs\ScrapeEntityJobConcerns;

use App\Facades\PageParser;
use App\Models\Entity;
use App\Models\Snapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait DataParsingStage
{
    protected function parseData(Entity $entity): bool
    {
        if (env('APP_DEBUG')) {
            dump('Parse data, entity '.$entity->id);
        }

        if (!$snapshot = $entity->currentSnapshot
            or !in_array($snapshot->file_extension, ['html', 'txt'])
        ) {
            return false;
        }

        $cleanHtmlFilePath = $this->getFilePathForCleanHtml($snapshot);
        if (!$cleanedHtml = Storage::get($cleanHtmlFilePath)) {
            return false;
        }

        $pageData = PageParser::parse($cleanedHtml);

        if (!Storage::put($this->getFilePathForPageData($snapshot), $pageData->toJson())) {
            return false;
        }

        $linkedUrls = $pageData->getLinkedPageUrls();
        $contentLength = strlen($pageData->getMarkdownContent());
        $linksCount = count($linkedUrls);
        if ($linksCount === 0 && $pageData->getMarkdownContent() !== '') {
            $linksCount = $this->countLinksInMarkdown($pageData->getMarkdownContent());
        }
        $mediaCount = $this->countMediaInMarkdown($pageData->getMarkdownContent());

        $saved = false;
        DB::transaction(function () use ($entity, $snapshot,
            $linksCount, $mediaCount, $contentLength,
            $pageData, &$saved
        ) {
            $entity->description = $pageData->getExcerpt();
            $entity->source_published_at = $pageData->getPublishedAt();
            $entity->source_updated_at = $pageData->getUpdatedAt();
            $entity->canonical_number = $pageData->getCanonicalNumber() ?? 0;

            $snapshot->links_count = $linksCount;
            $snapshot->media_count = $mediaCount;
            $snapshot->content_length = $contentLength;

            $saved = $entity->save() and $snapshot->save();
        });

        return $saved;
    }

    protected function getFilePathForPageData(Snapshot $snapshot): string
    {
        return 'snapshots/'.$snapshot->entity_id.'/'.$snapshot->id.'/page-data.json';
    }
}