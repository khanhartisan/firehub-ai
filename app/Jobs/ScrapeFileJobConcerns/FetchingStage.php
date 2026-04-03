<?php

namespace App\Jobs\ScrapeFileJobConcerns;

use App\Enums\ScrapingStatus;
use App\Facades\Scraper;
use App\Models\File;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;

trait FetchingStage
{
    /**
     * Fetch the file URL, persist the body to storage, and update metadata.
     *
     * @return bool True when fetch succeeded and the pipeline should continue.
     */
    protected function handleFileFetchingStage(File $file): bool
    {
        if (env('APP_DEBUG')) {
            dump('Fetching file '.$file->id);
        }

        $file->scraping_status = ScrapingStatus::FETCHING;
        DB::transaction(fn () => $file->save());

        $fetchStartedAt = microtime(true);

        try {
            $response = Scraper::fetch($file->url);
            $statusCode = $response->getStatusCode();
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);

            if ($statusCode >= 400) {
                $this->handleFileFetchingFailed($file, $statusCode, null, $fetchDurationMs, "HTTP {$statusCode}");

                return false;
            }

            $mimeType = $this->guessMimeTypeFromResponse($response);
            $extension = $this->guessFileExtensionByMimeType($mimeType);
            $relativePath = 'files/'.$file->id.'/data.bin';

            $body = $response->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }

            if (! Storage::put($relativePath, $body)) {
                throw new \RuntimeException('Unable to write file contents to storage');
            }

            $file->path = $relativePath;
            $file->mime_type = $mimeType;
            $file->extension = $extension;
            $file->size = Storage::size($relativePath);
            $file->fetch_duration_ms = $fetchDurationMs;
            $file->scraping_status = ScrapingStatus::PROCESSING;
            $file->scraped_at = now();

            return true;

        } catch (ConnectException $e) {
            Log::warning("ScrapeFileJob: Connect error for file [{$file->id}]: {$e->getMessage()}");
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);
            $this->handleFileFetchingFailed($file, null, ScrapingStatus::TIMEOUT, $fetchDurationMs, $e->getMessage());
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;

            Log::warning("ScrapeFileJob: Request error for file [{$file->id}]: {$e->getMessage()}");

            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);

            $responseBodySnippet = '';
            $response = $e->getResponse();
            if ($response !== null && $this->isResponseBodyReadable($response)) {
                $body = $response->getBody();
                if ($body->isReadable()) {
                    $body->rewind();
                    $responseBodySnippet = $body->read(10_000);
                }
            }

            $this->handleFileFetchingFailed(
                $file,
                $statusCode,
                null,
                $fetchDurationMs,
                $e->getMessage()."\n".$responseBodySnippet
            );
        } catch (\Throwable $e) {
            Log::error("ScrapeFileJob: Unexpected error for file [{$file->id}]: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);
            $errorLogs = $e->getMessage()."\n".$e->getTraceAsString();
            $this->handleFileFetchingFailed($file, null, ScrapingStatus::FAILED, $fetchDurationMs, $errorLogs);
        }

        return false;
    }

    protected function guessMimeTypeFromResponse(ResponseInterface $response): ?string
    {
        $contentType = $response->getHeaderLine('Content-Type');

        if (! $contentType) {
            return $this->guessMimeTypeFromResponseBody($response);
        }

        return strtolower(trim(explode(';', $contentType)[0]));
    }

    protected function guessMimeTypeFromResponseBody(ResponseInterface $response): ?string
    {
        $stream = $response->getBody();

        if (! $stream->isReadable()) {
            return null;
        }

        $pos = $stream->tell();
        $stream->rewind();

        $bytes = $stream->read(32);

        if ($stream->isSeekable()) {
            $stream->seek($pos);
        }

        $hex = bin2hex($bytes);

        $map = [
            'ffd8ff' => 'image/jpeg',
            '89504e470d0a1a0a' => 'image/png',
            '47494638' => 'image/gif',
            '424d' => 'image/bmp',
            '52494646' => 'image/webp',
            '25504446' => 'application/pdf',
            '504b0304' => 'application/zip',
            '504b0506' => 'application/zip',
            '504b0708' => 'application/zip',
            '0000001866747970' => 'video/mp4',
            '494433' => 'audio/mpeg',
            'fff1' => 'audio/aac',
            'fff9' => 'audio/aac',
            '1f8b08' => 'application/gzip',
            '425a68' => 'application/x-bzip2',
            '377abcaf271c' => 'application/x-7z-compressed',
            '3c3f786d6c' => 'application/xml',
            '3c68746d6c' => 'text/html',
            '3c21444f4354' => 'text/html',
        ];

        foreach ($map as $sig => $mime) {
            if (str_starts_with($hex, $sig)) {
                return $mime;
            }
        }

        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        if (preg_match('//u', $bytes)) {
            return 'text/plain';
        }

        return null;
    }

    protected function guessFileExtensionByMimeType(?string $mimeType): ?string
    {
        return match ($mimeType) {
            'text/plain' => 'txt',
            'text/html' => 'html',
            'text/css' => 'css',
            'text/csv' => 'csv',
            'text/xml' => 'xml',
            'text/markdown' => 'md',
            'application/json' => 'json',
            'application/ld+json' => 'jsonld',
            'application/xml' => 'xml',
            'application/x-yaml' => 'yaml',
            'application/yaml' => 'yaml',
            'application/javascript' => 'js',
            'text/javascript' => 'js',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/x-icon' => 'ico',
            'application/pdf' => 'pdf',
            'application/rtf' => 'rtf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/x-tar' => 'tar',
            'application/gzip' => 'gz',
            'application/x-gzip' => 'gz',
            'application/x-7z-compressed' => '7z',
            'application/x-rar-compressed' => 'rar',
            'font/woff' => 'woff',
            'font/woff2' => 'woff2',
            'font/ttf' => 'ttf',
            'font/otf' => 'otf',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'audio/webm' => 'webm',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/x-msvideo' => 'avi',
            'video/quicktime' => 'mov',
            'application/octet-stream' => 'bin',
            default => null,
        };
    }

    protected function handleFileFetchingFailed(File $file, ?int $statusCode, ?ScrapingStatus $status = null, ?int $fetchDurationMs = null, ?string $errorLogs = null): void
    {
        $status = $status ?? ($this->isBlockedStatus($statusCode) ? ScrapingStatus::BLOCKED : ScrapingStatus::FAILED);
        $maxAttempts = config('queue.max_scrape_attempts');

        $file->increment('attempts');
        $file->refresh();

        $status = ($file->attempts >= $maxAttempts) ? ScrapingStatus::FAILED : $status;

        DB::transaction(function () use ($file, $status, $fetchDurationMs, $errorLogs) {
            $file->updateQuietly([
                'scraping_status' => $status,
                'fetch_duration_ms' => $fetchDurationMs,
                'error_logs' => $errorLogs,
            ]);
        });

        if ($file->attempts >= $maxAttempts) {
            Log::warning("ScrapeFileJob: File [{$file->id}] exceeded max attempts ({$file->attempts}/{$maxAttempts}), stopping.");
        }
    }

    private function isResponseBodyReadable(ResponseInterface $response): bool
    {
        $contentTypes = $response->getHeader('Content-Type');
        $contentType = (string) ($contentTypes[0] ?? '');
        $contentType = strtolower(trim(explode(';', $contentType)[0]));

        if ($contentType === '') {
            return false;
        }

        $readableApplicationTypes = [
            'application/json',
            'application/xml',
            'application/javascript',
            'application/xhtml+xml',
        ];

        return str_starts_with($contentType, 'text/')
            || in_array($contentType, $readableApplicationTypes, true);
    }

    protected function isBlockedStatus(?int $statusCode): bool
    {
        return $statusCode === 403 || $statusCode === 429;
    }
}
