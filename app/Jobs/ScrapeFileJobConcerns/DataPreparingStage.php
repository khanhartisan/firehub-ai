<?php

namespace App\Jobs\ScrapeFileJobConcerns;

use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;

trait DataPreparingStage
{
    /**
     * Allowed image extensions after fetch (lowercase).
     *
     * @var list<string>
     */
    protected array $scrapedImageExtensions = [
        'jpeg', 'jpg', 'png', 'webp', 'avif', 'gif', 'bmp', 'tiff',
    ];

    /**
     * Prepare scraped file data: only images proceed; others are rejected.
     * On success, writes a resized / normalized JPEG to {@see File::preparedImageStoragePath()}.
     *
     * @return bool True when preparation succeeded and the pipeline should continue.
     */
    protected function handleFileDataPreparingStage(File $file): bool
    {
        if (env('APP_DEBUG')) {
            dump('Data preparing, file '.$file->id);
        }

        $ext = strtolower((string) ($file->extension ?? ''));

        if (! in_array($ext, $this->scrapedImageExtensions, true)) {
            return false;
        }

        if (! $file->path || ! Storage::exists($file->path)) {
            return false;
        }

        $contents = Storage::get($file->path);
        if ($contents === null || $contents === '') {
            return false;
        }

        $preparedPath = $file->preparedImageStoragePath();

        if (! $this->writePreparedImageForScrape($contents, $preparedPath)) {
            return false;
        }

        return true;
    }

    /**
     * Downscale, normalize orientation, and encode as JPEG for downstream stages (same strategy as page snapshots).
     */
    protected function writePreparedImageForScrape(string $contents, string $preparedPath): bool
    {
        if (! class_exists(Imagick::class)) {
            return Storage::put($preparedPath, $contents);
        }

        try {
            $image = new Imagick;
            $image->readImageBlob($contents);

            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
                $first = $image->getImage();
                $image->clear();
                $image->destroy();
                $image = $first;
            }

            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            $this->downscaleImagickImageForScrape($image, 1024);
            $image = $this->normalizeImagickForVisionForScrape($image);

            $blob = $image->getImagesBlob();
            $image->clear();
            $image->destroy();

            return Storage::put($preparedPath, $blob);
        } catch (ImagickException|\Throwable $e) {
            Log::warning('ScrapeFileJob: Imagick prepare failed, falling back to original bytes: '.$e->getMessage());

            return Storage::put($preparedPath, $contents);
        }
    }

    protected function downscaleImagickImageForScrape(Imagick $image, int $maxDim): void
    {
        $w = (int) $image->getImageWidth();
        $h = (int) $image->getImageHeight();
        if ($w <= 0 || $h <= 0) {
            return;
        }

        $scale = min(1.0, $maxDim / max($w, $h));
        $newW = max(1, (int) floor($w * $scale));
        $newH = max(1, (int) floor($h * $scale));
        if ($newW === $w && $newH === $h) {
            return;
        }

        $image->resizeImage($newW, $newH, Imagick::FILTER_LANCZOS, 1);
    }

    protected function normalizeImagickForVisionForScrape(Imagick $image): Imagick
    {
        try {
            $image->setImageBackgroundColor('white');
            $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            if ($flattened instanceof Imagick) {
                $image->clear();
                $image->destroy();
                $image = $flattened;
            }
        } catch (\Throwable) {
        }

        $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $image->setImageFormat('jpeg');
        $image->setImageCompression(Imagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality(82);
        $image->stripImage();

        return $image;
    }
}
