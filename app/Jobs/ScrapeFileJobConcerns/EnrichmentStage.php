<?php

namespace App\Jobs\ScrapeFileJobConcerns;

use App\Facades\FileVision;
use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait EnrichmentStage
{
    /**
     * Describe the prepared image via FileVision and persist the text on the file record.
     *
     * @return bool True when enrichment succeeded and the pipeline should continue.
     */
    protected function handleFileEnrichmentStage(File $file): bool
    {
        if (env('APP_DEBUG')) {
            dump('Enrichment, file '.$file->id);
        }

        $preparedPath = static::preparedImageStoragePath($file);

        if (! Storage::exists($preparedPath)) {
            Log::warning("ScrapeFileJob: prepared image missing for file [{$file->id}]");

            return false;
        }

        try {
            $information = FileVision::describe($preparedPath);
        } catch (\Throwable $e) {
            Log::warning("ScrapeFileJob: FileVision failed for file [{$file->id}]: {$e->getMessage()}");

            return false;
        }

        $file->description = $information->getDescription() ?? '';

        return true;
    }

    public static function preparedImageStoragePath(File $file): string
    {
        return 'files/'.$file->id.'/prepared-image.jpg';
    }
}
