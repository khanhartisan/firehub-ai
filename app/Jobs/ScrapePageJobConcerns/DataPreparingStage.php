<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Models\Page;
use App\Models\Snapshot;
use App\Utils\HtmlCleaner;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;

trait DataPreparingStage
{
    protected function handleDataPreparingStage(Page $page): bool
    {
        if (env('APP_DEBUG')) {
            dump('Prepare data, entity '.$page->id);
        }

        if (!$snapshot = $page->currentSnapshot) {
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
            return $this->prepareImageSnapshot($snapshot, $contents);
        }

        return false;
    }

    /**
     * Prepare an image snapshot for downstream vision APIs.
     *
     * - Keeps output size predictable (downscale to max dimension)
     * - Normalizes orientation
     * - Converts to JPEG (broad compatibility)
     * - Falls back to original bytes on failure
     */
    protected function prepareImageSnapshot(Snapshot $snapshot, string $contents): bool
    {
        $preparedPath = $this->getFilePathForPreparedImage($snapshot);

        // If Imagick isn't available, just persist the original bytes so the pipeline can continue.
        if (!class_exists(Imagick::class)) {
            return Storage::put($preparedPath, $contents);
        }

        try {
            $image = new Imagick();
            $image->readImageBlob($contents);

            // Use the first frame for animated formats to keep size predictable for vision APIs.
            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
                $first = $image->getImage();
                $image->clear();
                $image->destroy();
                $image = $first;
            }

            // Normalize orientation when supported (e.g., JPEG EXIF orientation).
            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            $this->downscaleImagickImage($image, 1024);
            $image = $this->normalizeImagickForVision($image);

            $blob = $image->getImagesBlob();
            $image->clear();
            $image->destroy();

            return Storage::put($preparedPath, $blob);
        } catch (ImagickException|\Throwable $e) {
            // If processing fails, fall back to saving original bytes.
            return Storage::put($preparedPath, $contents);
        }
    }

    protected function downscaleImagickImage(Imagick $image, int $maxDim): void
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

    protected function normalizeImagickForVision(Imagick $image): Imagick
    {
        // Convert to JPEG for broad vision API compatibility and smaller payloads.
        // Flatten transparency against white to avoid black backgrounds.
        try {
            $image->setImageBackgroundColor('white');
            $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            if ($flattened instanceof Imagick) {
                $image->clear();
                $image->destroy();
                $image = $flattened;
            }
        } catch (\Throwable $e) {
            // If flattening fails, continue without it.
        }

        $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $image->setImageFormat('jpeg');
        $image->setImageCompression(Imagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality(82);
        $image->stripImage();

        return $image;
    }

    protected function getFilePathForCleanHtml(Snapshot $snapshot): string
    {
        return 'snapshots/'.$snapshot->page_id.'/'.$snapshot->id.'/clean.html';
    }

    protected function getFilePathForPreparedImage(Snapshot $snapshot): string
    {
        return 'snapshots/'.$snapshot->page_id.'/'.$snapshot->id.'/prepared-image.jpg';
    }
}