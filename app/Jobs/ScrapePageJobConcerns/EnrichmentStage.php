<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Enums\ScrapableType;
use App\Facades\FileVision;
use App\Facades\PageClassifier;
use App\Models\Page;
use App\Models\Snapshot;
use App\Models\Tag;
use App\Utils\Debugger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait EnrichmentStage
{
    protected function handleEnrichmentStage(Page $page): bool
    {
        Debugger::devConsoleDump('Enrichment, page '.$page->id);

        if (!$snapshot = $page->currentSnapshot) {
            return false;
        }

        $isSaved = false;

        // Classifying text only
        if (!in_array($snapshot->file_extension, ['html', 'txt'])) {
            return false;
        }

        if (!$cleanedHtmlFilePath = $this->getFilePathForCleanHtml($snapshot)
            or !$cleanedHtml = Storage::get($cleanedHtmlFilePath)
        ) {
            return false;
        }

        $classification = PageClassifier::classify($cleanedHtml);

        if (!Storage::put(
            $this->getFilePathForPageClassificationResult($snapshot),
            $classification->toJson()
        )) {
            return false;
        }

        DB::transaction(function () use ($classification, $page, &$isSaved) {

            // Sync tags
            $tagIds = collect($classification->getTags())
                ->map(fn (string $name): string
                => Tag::query()
                    ->firstOrCreate(['name' => $name])
                    ->id
                )
                ->all();
            $page->tags()->sync($tagIds);

            $page->type = ScrapableType::TEXT;
            $page->page_type = $classification->getPageType();
            $page->content_type = $classification->getContentType();
            $page->temporal = $classification->getTemporal();
            $page->language = $classification->getLanguage();
            $isSaved = $page->save();
        });

        return $isSaved;
    }

    protected function getFilePathForPageClassificationResult(Snapshot $snapshot): string
    {
        return 'snapshots/'.$snapshot->page_id.'/'.$snapshot->id.'/page-classification.json';
    }

    protected function getFilePathForFileInformation(Snapshot $snapshot): string
    {
        return 'snapshots/'.$snapshot->page_id.'/'.$snapshot->id.'/file-information.json';
    }
}