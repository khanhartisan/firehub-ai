<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Enums\ScrapingStatus;
use App\Models\File;
use Illuminate\Support\Facades\Storage;

trait InteractsWithArticleBuildFiles
{
    protected function resolveOrCreateFileFromStoragePath(string $path, ?string $description = null): File
    {
        if ($file = File::query()->where('path', $path)->first()) {
            return $file;
        }

        $url = 'storage://'.$path;
        $urlHash = File::getUrlHash($url);

        if ($file = File::query()->where('url_hash', $urlHash)->first()) {
            if (! is_string($file->path) || $file->path === '') {
                $file->path = $path;
                $file->saveQuietly();
            }

            return $file;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: null;
        $mimeType = Storage::exists($path) ? (Storage::mimeType($path) ?: null) : null;

        return File::query()->create([
            'url' => $url,
            'path' => $path,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => Storage::exists($path) ? Storage::size($path) : null,
            'scraping_status' => ScrapingStatus::SUCCESS,
            'scraped_at' => now(),
            'description' => $description,
        ]);
    }
}
