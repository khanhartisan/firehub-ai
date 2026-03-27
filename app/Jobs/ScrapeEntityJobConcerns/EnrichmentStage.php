<?php

namespace App\Jobs\ScrapeEntityJobConcerns;

use App\Enums\EntityType;
use App\Facades\FileVision;
use App\Facades\PageClassifier;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait EnrichmentStage
{
    protected function enrich(Entity $entity): bool
    {
        if (env('APP_DEBUG')) {
            dump('Enrichment, entity '.$entity->id);
        }

        if (!$snapshot = $entity->currentSnapshot) {
            return false;
        }

        $isSaved = false;

        // Classifying text
        if (in_array($snapshot->file_extension, ['html', 'txt'])) {
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

            DB::transaction(function () use ($classification, $entity, &$isSaved) {

                // Sync tags
                $tagIds = collect($classification->getTags())
                    ->map(fn (string $name): string
                    => Tag::query()
                        ->firstOrCreate(['name' => $name])
                        ->id
                    )
                    ->all();
                $entity->tags()->sync($tagIds);

                $entity->type = EntityType::PAGE;
                $entity->page_type = $classification->getPageType();
                $entity->content_type = $classification->getContentType();
                $entity->temporal = $classification->getTemporal();
                $isSaved = $entity->save();
            });
        }

        return $isSaved;
    }

    protected function getFilePathForPageClassificationResult(Snapshot $snapshot): string
    {
        return 'snapshots/'.$snapshot->entity_id.'/'.$snapshot->id.'/page-classification.json';
    }
}