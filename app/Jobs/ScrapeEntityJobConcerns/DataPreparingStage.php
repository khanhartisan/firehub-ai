<?php

namespace App\Jobs\ScrapeEntityJobConcerns;

use App\Models\Entity;
use App\Models\Snapshot;
use App\Utils\HtmlCleaner;
use Illuminate\Support\Facades\Storage;

trait DataPreparingStage
{
    protected function prepareData(Entity $entity): bool
    {
        if (!$snapshot = $entity->currentSnapshot) {
            return false;
        }

        if (!$contents = Storage::get($snapshot->file_path)) {
            return false;
        }

        // Prepare for text
        if (in_array($snapshot->file_extension, ['html', 'txt'])) {
            return Storage::put(
                $this->getFilePathForCleanHtml($snapshot),
                HtmlCleaner::clean($contents)
            );
        }

        // Prepare for image
        if (in_array($snapshot->file_extension, ['jpeg', 'jpg', 'png', 'webp', 'avif', 'gif', 'bmp', 'tiff'])) {
            // TODO: Prepare for images (resizing)
        }

        return false;
    }

    protected function getFilePathForCleanHtml(Snapshot $snapshot): string
    {
        return 'snapshots/'.$snapshot->entity_id.'/'.$snapshot->id.'/clean.html';
    }

    protected function getFilePathForPreparedImage(Snapshot $snapshot): string
    {
        return 'snapshot/'.$snapshot->entity_id.'/'.$snapshot->id.'/prepared-image.'.$snapshot->file_extension;
    }
}