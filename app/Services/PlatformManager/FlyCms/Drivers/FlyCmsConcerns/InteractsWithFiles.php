<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\UpdateFileData;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
use App\Utils\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;

trait InteractsWithFiles
{
    /**
     * @throws FlyCmsException
     */
    public function showFile(string $fileId): ?FileResource
    {
        /** @var ?FileResource */
        return $this->showResource(FileResource::class, $fileId);
    }

    /**
     * @throws FlyCmsException
     */
    public function createFile(mixed $data, CreateFileData $createFileData): FileResource
    {
        $content = $this->readFileUploadData($data);
        $mutationData = $createFileData->getData() ?? [];
        $ext = (string) ($mutationData['ext'] ?? 'jpg');
        $filename = is_string($mutationData['filename'] ?? null) && $mutationData['filename'] !== ''
            ? $mutationData['filename']
            : null;
        $uploadFilename = $filename ?? ('upload.'.$ext);
        $storageId = $this->resolveFlyCmsStorageId();

        $signatureResponse = $this->requestFileUploadSignature($storageId, $ext, $filename);
        $endpoint = (string) ($signatureResponse['endpoint'] ?? '');
        $signature = $signatureResponse['signature'] ?? null;

        if ($endpoint === '' || ! is_array($signature) || $signature === []) {
            throw new FlyCmsException('Failed to request file upload signature');
        }

        $this->uploadFileToSignedStorage($endpoint, $signature, $content, $uploadFilename);

        $key = (string) ($signature['key'] ?? $signature['Key'] ?? '');

        if ($key === '') {
            throw new FlyCmsException('Failed to resolve uploaded file key');
        }

        $createPayload = array_filter([
            'storage_id' => $storageId,
            'key' => $key,
            'code' => $mutationData['code'] ?? null,
            'type' => $this->resolveFileTypeFromExt($ext),
            'information' => $mutationData['information'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        $response = $this->sendApiRequest('POST', FileResource::resourceNamespace(), [
            'json' => $createPayload,
        ]);

        if (! $responseData = $this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to create file (Unknown error)');
        }

        return FileResource::fromArray($responseData);
    }

    /**
     * @throws FlyCmsException
     */
    public function updateFile(string $fileId, UpdateFileData $updateFileData): FileResource
    {
        /** @var FileResource */
        return $this->updateResource(
            FileResource::class,
            $fileId,
            $updateFileData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function listFiles(int         $page = 1,
                              int         $limit = 100,
                              ?int        $orderDirection = null,
                              ?FileFilter $fileFilter = null): array
    {
        return $this->listResources(
            FileResource::class,
            $page,
            $limit,
            $this->resolveFileSort($orderDirection),
            $fileFilter
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteFile(string $fileId): bool
    {
        return $this->deleteResource(FileResource::class, $fileId);
    }

    /**
     * @throws FlyCmsException
     */
    protected function attachFile(string $fileId, string $resourceType, string $resourceId, ?array $metadata = null): void
    {
        $payload = array_filter([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata,
        ], static fn (mixed $value): bool => $value !== null);

        $response = $this->sendApiRequest('POST', FileResource::resourceNamespace().'/'.$fileId.':attach', [
            'json' => $payload,
        ]);

        if (! $this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to attach file to resource.');
        }
    }

    protected function resolveFileSort(?int $orderDirection): ?string
    {
        if ($orderDirection === null) {
            return null;
        }

        return $orderDirection === -1 ? '-sorting' : 'sorting';
    }

    protected function readFileUploadData(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_resource($data)) {
            $content = stream_get_contents($data);

            return is_string($content) ? $content : '';
        }

        throw new InvalidArgumentException('File data must be a string or stream resource.');
    }

    protected function resolveFlyCmsStorageId(): string
    {
        $config = $this->getConfig();

        if (! $config instanceof Config) {
            throw new FlyCmsException('FlyCms config is not set');
        }

        $storageId = $config->getStorageId();

        if (! is_string($storageId) || $storageId === '') {
            throw new FlyCmsException('FlyCms storage_id is not configured');
        }

        return $storageId;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FlyCmsException
     */
    protected function requestFileUploadSignature(string $storageId, string $ext, ?string $filename): array
    {
        $payload = [
            'storage_id' => $storageId,
            'ext' => $ext,
        ];

        if ($filename !== null && $filename !== '') {
            $payload['filename'] = $filename;
        }

        $response = $this->sendApiRequest('POST', FileResource::resourceNamespace().':signature', [
            'json' => $payload,
        ]);

        $data = Json::decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $signature
     *
     * @throws FlyCmsException
     */
    protected function uploadFileToSignedStorage(string $endpoint, array $signature, string $content, string $filename): void
    {
        $multipart = [];

        foreach ($signature as $name => $value) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $multipart[] = [
                'name' => $name,
                'contents' => is_scalar($value) || $value === null ? (string) $value : '',
            ];
        }

        $multipart[] = [
            'name' => 'file',
            'contents' => $content,
            'filename' => $filename,
        ];

        try {
            $response = (new Client)->request('POST', $endpoint, [
                'multipart' => $multipart,
            ]);
        } catch (RequestException $exception) {
            throw new FlyCmsException('Failed to upload file to storage', $exception);
        } catch (GuzzleException $exception) {
            throw new FlyCmsException('Failed to upload file to storage', $exception);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new FlyCmsException('Failed to upload file to storage');
        }
    }

    protected function resolveFileTypeFromExt(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
            'mp4', 'webm' => 'video',
            default => 'unknown',
        };
    }
}
