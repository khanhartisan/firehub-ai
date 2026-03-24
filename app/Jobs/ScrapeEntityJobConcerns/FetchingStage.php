<?php

namespace App\Jobs\ScrapeEntityJobConcerns;

use App\Enums\ScrapingStatus;
use App\Facades\Scraper;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Utils\Json;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

trait FetchingStage
{
    protected function handleFetchingStage(Entity $entity): ?Snapshot
    {
        if (env('APP_DEBUG')) {
            dump('Fetching, entity '.$entity->id);
        }

        // Check the current scraping status
        if ($entity->scraping_status !== ScrapingStatus::QUEUED) {
            Log::debug("ScrapeEntityJob: Entity [{$entity->id}] status is {$entity->scraping_status->name}, skipping");
            return null;
        }

        // Update scraping status
        $entity->scraping_status = ScrapingStatus::FETCHING;
        DB::transaction(fn () => $entity->save());

        $fetchStartedAt = microtime(true);
        try {

            $response = method_exists($this, 'fetchUrl')
                ? $this->fetchUrl($entity->url)
                : Scraper::fetch($entity->url);
            $statusCode = $response->getStatusCode();
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);

            if ($statusCode >= 400) {
                $this->handleEntityFetchingFailed($entity, $statusCode, null, $fetchDurationMs, "HTTP {$statusCode}");
                return null;
            }

            return $this->createSnapshot($entity, $response, $fetchDurationMs);

        } catch (ConnectException $e) {
            Log::warning("ScrapeEntityJob: Connect error for entity [{$entity->id}]: {$e->getMessage()}");
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);
            $this->handleEntityFetchingFailed($entity, null, ScrapingStatus::TIMEOUT, $fetchDurationMs, $e->getMessage());
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;

            Log::warning("ScrapeEntityJob: Request error for entity [{$entity->id}]: {$e->getMessage()}");

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

            $this->handleEntityFetchingFailed(
                $entity,
                $statusCode,
                null,
                $fetchDurationMs,
                $e->getMessage()."\n".$responseBodySnippet
            );
        } catch (\Throwable $e) {
            Log::error("ScrapeEntityJob: Unexpected error for entity [{$entity->id}]: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            $fetchDurationMs = (int) round((microtime(true) - $fetchStartedAt) * 1000);
            $errorLogs = $e->getMessage() . "\n" . $e->getTraceAsString();
            $this->handleEntityFetchingFailed($entity, null, ScrapingStatus::FAILED, $fetchDurationMs, $errorLogs);
        }

        return null;
    }

    protected function createSnapshot(Entity $entity,
                                      ResponseInterface $response,
                                      int $fetchDurationMs = 0): Snapshot
    {
        $snapshot = new Snapshot();
        $snapshot->id = strtolower(Str::ulid());
        $snapshot->entity_id = $entity->id;
        $snapshot->scraping_status = ScrapingStatus::SUCCESS;
        $snapshot->version = $entity->version_index + 1;
        $snapshot->file_path = 'snapshots/'.$entity->id.'/'.$snapshot->id.'/data.bin';

        $snapshot->file_mime_type = $this->guessMimeTypeFromResponse($response);
        $snapshot->file_extension = $this->guessFileExtensionByMimeType($snapshot->file_mime_type);

        // Save data to storage
        if (!Storage::put($snapshot->file_path, $response->getBody())) {
            throw new \Exception("Unable to write snapshot file");
        }

        $snapshot->file_size = Storage::size($snapshot->file_path);
        $snapshot->fetch_duration_ms = $fetchDurationMs;

        DB::transaction(fn () => $snapshot->save());

        return $snapshot;
    }

    protected function guessMimeTypeFromResponse(ResponseInterface $response): ?string
    {
        $contentType = $response->getHeaderLine('Content-Type');

        if (!$contentType) {
            return $this->guessMimeTypeFromResponseBody($response);
        }

        return strtolower(trim(explode(';', $contentType)[0]));
    }

    protected function guessMimeTypeFromResponseBody(ResponseInterface $response): ?string
    {
        $stream = $response->getBody();

        if (!$stream->isReadable()) {
            return null;
        }

        $pos = $stream->tell();
        $stream->rewind();

        $bytes = $stream->read(32); // đủ cho hầu hết signatures

        // restore pointer
        if ($stream->isSeekable()) {
            $stream->seek($pos);
        }

        $hex = bin2hex($bytes);

        $map = [
            // images
            'ffd8ff' => 'image/jpeg',
            '89504e470d0a1a0a' => 'image/png',
            '47494638' => 'image/gif',
            '424d' => 'image/bmp',
            '52494646' => 'image/webp', // RIFF....WEBP

            // documents
            '25504446' => 'application/pdf',
            '504b0304' => 'application/zip', // zip / docx / xlsx / jar
            '504b0506' => 'application/zip',
            '504b0708' => 'application/zip',

            // media
            '0000001866747970' => 'video/mp4',
            '494433' => 'audio/mpeg',
            'fff1' => 'audio/aac',
            'fff9' => 'audio/aac',

            // archives
            '1f8b08' => 'application/gzip',
            '425a68' => 'application/x-bzip2',
            '377abcaf271c' => 'application/x-7z-compressed',

            // text
            '3c3f786d6c' => 'application/xml', // <?xml
            '3c68746d6c' => 'text/html',       // <html
            '3c21444f4354' => 'text/html',     // <!DOCTYPE
        ];

        foreach ($map as $sig => $mime) {
            if (str_starts_with($hex, $sig)) {
                return $mime;
            }
        }

        // special case: webp (RIFF....WEBP)
        if (substr($bytes, 0, 4) === "RIFF" && substr($bytes, 8, 4) === "WEBP") {
            return 'image/webp';
        }

        // fallback: text detection
        if (preg_match('//u', $bytes)) {
            return 'text/plain';
        }

        return null;
    }

    protected function guessFileExtensionByMimeType(?string $mimeType): ?string
    {
        return match ($mimeType) {
            // text
            'text/plain' => 'txt',
            'text/html' => 'html',
            'text/css' => 'css',
            'text/csv' => 'csv',
            'text/xml' => 'xml',
            'text/markdown' => 'md',

            // json / data
            'application/json' => 'json',
            'application/ld+json' => 'jsonld',
            'application/xml' => 'xml',
            'application/x-yaml' => 'yaml',
            'application/yaml' => 'yaml',

            // javascript
            'application/javascript' => 'js',
            'text/javascript' => 'js',

            // images
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

            // documents
            'application/pdf' => 'pdf',
            'application/rtf' => 'rtf',

            // office
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',

            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',

            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',

            // archives
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/x-tar' => 'tar',
            'application/gzip' => 'gz',
            'application/x-gzip' => 'gz',
            'application/x-7z-compressed' => '7z',
            'application/x-rar-compressed' => 'rar',

            // fonts
            'font/woff' => 'woff',
            'font/woff2' => 'woff2',
            'font/ttf' => 'ttf',
            'font/otf' => 'otf',

            // audio
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'audio/webm' => 'webm',

            // video
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/x-msvideo' => 'avi',
            'video/quicktime' => 'mov',

            // binary
            'application/octet-stream' => 'bin',

            default => null,
        };
    }

    /**
     * Mark entity as failed, create a snapshot with the appropriate status for history/evaluation,
     * apply backoff or stop if max attempts reached.
     */
    protected function handleEntityFetchingFailed(Entity $entity, ?int $statusCode, ?ScrapingStatus $status = null, ?int $fetchDurationMs = null, ?string $errorLogs = null): void
    {
        $status = $status ?? ($this->isBlockedStatus($statusCode) ? ScrapingStatus::BLOCKED : ScrapingStatus::FAILED);
        $maxAttempts = config('queue.max_scrape_attempts');

        $entity->increment('attempts');
        $entity->refresh();

        DB::transaction(function () use ($entity, $status, $fetchDurationMs, $errorLogs, $maxAttempts) {
            $version = $entity->version_index + 1;
            // Always create a snapshot with the failure status so it can be used as history for evaluating entities.
            $snapshot = new Snapshot([
                'id' => strtolower(Str::ulid()),
                'entity_id' => $entity->id,
                'scraping_status' => $status,
                'version' => $version,
                'fetch_duration_ms' => $fetchDurationMs,
                'error_logs' => $errorLogs,
            ]);
            $snapshot->save();

            if ($entity->attempts >= $maxAttempts) {
                $entity->update([
                    'scraping_status' => $status,
                    'next_scrape_at' => null,
                ]);
            } else {
                $delaySeconds = $this->backoffSecondsForAttempt($entity->attempts);
                $entity->update([
                    'scraping_status' => $status,
                    'next_scrape_at' => Carbon::now()->addSeconds($delaySeconds),
                ]);
            }
        });

        if ($entity->attempts >= $maxAttempts) {
            Log::warning("ScrapeEntityJob: Entity [{$entity->id}] exceeded max attempts ({$entity->attempts}/{$maxAttempts}), stopping.");
        }
    }

    /**
     * Whether the response body is human-readable (text-like) and safe to include in error logs.
     */
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

    /**
     * Exponential backoff in seconds: base * 2^(attempt-1), capped at 7 days.
     */
    protected function backoffSecondsForAttempt(int $attempt): int
    {
        $baseSeconds = 3600;   // 1 hour
        $maxSeconds = 86400 * 7; // 7 days
        $delay = $baseSeconds * (2 ** ($attempt - 1));

        return (int) min($delay, $maxSeconds);
    }
}