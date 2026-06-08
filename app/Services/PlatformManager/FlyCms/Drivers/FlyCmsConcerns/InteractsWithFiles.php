<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\UpdateFileData;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
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
            : 'upload.'.$ext;

        $response = $this->sendApiRequest('POST', FileResource::resourceNamespace(), [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $content,
                    'filename' => $filename,
                ],
                [
                    'name' => 'data',
                    'contents' => json_encode($createFileData->toArray()),
                    'headers' => ['Content-Type' => 'application/json'],
                ],
            ],
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
}
