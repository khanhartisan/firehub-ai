<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\UpdateFileData;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;

interface FileManager
{
    public function showFile(string $fileId): ?FileResource;

    /**
     * @param mixed $data As a string or resource (stream)
     * @param CreateFileData $createFileData
     * @return FileResource
     */
    public function createFile(mixed $data, CreateFileData $createFileData): FileResource;

    public function updateFile(string $fileId, UpdateFileData $updateFileData): FileResource;

    /**
     * @param int $page
     * @param int $limit
     * @param ?int $orderDirection null: default, -1: newer first, 1: older first
     * @param FileFilter|null $fileFilter
     * @return FileResource[]
     */
    public function listFiles(int $page = 1,
                              int $limit = 100,
                              ?int $orderDirection = null,
                              ?FileFilter $fileFilter = null): array;

    public function deleteFile(string $fileId): bool;
}