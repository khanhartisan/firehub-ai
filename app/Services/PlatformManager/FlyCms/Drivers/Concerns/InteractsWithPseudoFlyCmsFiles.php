<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\Concerns;

use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\UpdateFileData;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
use Illuminate\Support\Str;

trait InteractsWithPseudoFlyCmsFiles
{
    public function showFile(string $fileId): ?FileResource
    {
        $file = self::$files[$fileId] ?? null;

        if ($file === null) {
            return null;
        }

        return $this->toFileResource($file);
    }

    public function createFile(mixed $data, CreateFileData $createFileData): FileResource
    {
        $content = $this->readFileData($data);
        $mutationData = $createFileData->getData() ?? [];
        $ext = (string) ($mutationData['ext'] ?? 'jpg');
        $fileId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $key = 'uploads/'.($mutationData['filename'] ?? $fileId).'.'.$ext;

        $file = array_merge($this->defaultFileAttributes(), [
            'id' => $fileId,
            'user_id' => $this->resolveAuthenticatedFlyCmsUserId(),
            'code' => $mutationData['code'] ?? null,
            'key' => $key,
            'type' => $this->resolveFileTypeFromExt($ext),
            'mime' => $this->resolveMimeFromExt($ext),
            'size' => strlen($content),
            'information' => $mutationData['information'] ?? null,
            'is_uploaded' => true,
            'url' => $this->pseudoFileUrl($key),
            'created_at' => $now,
        ]);

        self::$files[$fileId] = $file;

        return $this->toFileResource($file);
    }

    public function updateFile(string $fileId, UpdateFileData $updateFileData): FileResource
    {
        $file = self::$files[$fileId] ?? null;

        if ($file === null) {
            throw new \InvalidArgumentException("File [{$fileId}] not found.");
        }

        $data = array_filter(
            $updateFileData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        $file = array_merge($file, $data);

        self::$files[$fileId] = $file;

        return $this->toFileResource($file);
    }

    /**
     * @return FileResource[]
     */
    public function listFiles(int $page = 1,
                              int $limit = 100,
                              ?int $orderDirection = null,
                              ?FileFilter $fileFilter = null): array
    {
        $files = array_values(self::$files);

        if ($fileFilter !== null) {
            $files = $this->applyFileFilter($files, $fileFilter);
        }

        if ($orderDirection !== null) {
            usort($files, function (array $left, array $right) use ($orderDirection): int {
                $leftTime = strtotime((string) ($left['created_at'] ?? ''));
                $rightTime = strtotime((string) ($right['created_at'] ?? ''));

                return $orderDirection === -1
                    ? $rightTime <=> $leftTime
                    : $leftTime <=> $rightTime;
            });
        }

        $offset = max(0, ($page - 1) * $limit);
        $files = array_slice($files, $offset, $limit);

        return array_map(
            fn (array $file): FileResource => $this->toFileResource($file),
            $files
        );
    }

    public function deleteFile(string $fileId): FileResource
    {
        $file = self::$files[$fileId] ?? null;

        if ($file === null) {
            throw new \InvalidArgumentException("File [{$fileId}] not found.");
        }

        $resource = $this->toFileResource($file);

        unset(self::$files[$fileId]);

        return $resource;
    }
    protected function defaultFileAttributes(): array
    {
        return [
            'code' => null,
            'user_id' => null,
            'key' => '',
            'type' => 'unknown',
            'mime' => 'application/octet-stream',
            'size' => 0,
            'information' => null,
            'is_uploaded' => false,
            'url' => null,
            'post_id' => null,
            'created_at' => null,
        ];
    }

    protected function toFileResource(array $file): FileResource
    {
        return new FileResource($this->fileRecordForOutput($file));
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    protected function fileRecordForOutput(array $file): array
    {
        unset($file['post_id'], $file['created_at']);

        return $file;
    }

    protected function pseudoFileUrl(string $key): string
    {
        return 'https://cdn.pseudo.flycms.test/'.$key;
    }

    protected function resolveMimeFromExt(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }

    protected function resolveFileTypeFromExt(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
            'mp4', 'webm' => 'video',
            default => 'unknown',
        };
    }

    protected function readFileData(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_resource($data)) {
            $content = stream_get_contents($data);

            return is_string($content) ? $content : '';
        }

        throw new \InvalidArgumentException('File data must be a string or stream resource.');
    }
    protected function seedSampleFiles(): void
    {
        $older = now()->subDays(2)->toIso8601String();
        $newer = now()->subDay()->toIso8601String();

        self::$files = [
            '01J00000000000000000000071' => array_merge($this->defaultFileAttributes(), [
                'id' => '01J00000000000000000000071',
                'code' => 'hero-banner',
                'user_id' => '01J00000000000000000000061',
                'key' => 'uploads/hero-banner.jpg',
                'type' => 'image',
                'mime' => 'image/jpeg',
                'size' => 2048,
                'information' => [
                    'alt' => 'Sample blog hero image',
                ],
                'is_uploaded' => true,
                'url' => $this->pseudoFileUrl('uploads/hero-banner.jpg'),
                'post_id' => '01J00000000000000000000051',
                'created_at' => $older,
            ]),
            '01J00000000000000000000072' => array_merge($this->defaultFileAttributes(), [
                'id' => '01J00000000000000000000072',
                'code' => null,
                'user_id' => '01J00000000000000000000062',
                'key' => 'uploads/weekend-ideas.webp',
                'type' => 'image',
                'mime' => 'image/webp',
                'size' => 4096,
                'information' => null,
                'is_uploaded' => true,
                'url' => $this->pseudoFileUrl('uploads/weekend-ideas.webp'),
                'post_id' => '01J00000000000000000000052',
                'created_at' => $newer,
            ]),
            '01J00000000000000000000073' => array_merge($this->defaultFileAttributes(), [
                'id' => '01J00000000000000000000073',
                'code' => 'storefront-intro',
                'user_id' => '01J00000000000000000000062',
                'key' => 'uploads/storefront-intro.mp4',
                'type' => 'video',
                'mime' => 'video/mp4',
                'size' => 8192,
                'information' => [
                    'duration' => 12,
                ],
                'is_uploaded' => false,
                'url' => null,
                'post_id' => null,
                'created_at' => now()->toIso8601String(),
            ]),
        ];
    }
    protected function applyFileFilter(array $files, FileFilter $fileFilter): array
    {
        $filterData = $fileFilter->getFilterData();

        if (isset($filterData['user_id']) && is_string($filterData['user_id']) && $filterData['user_id'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['user_id'] ?? null) === $filterData['user_id']
            ));
        }

        if (isset($filterData['ids']) && is_string($filterData['ids']) && $filterData['ids'] !== '') {
            $ids = array_map('trim', explode(',', $filterData['ids']));
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => in_array($file['id'] ?? null, $ids, true)
            ));
        }

        if (isset($filterData['post_id']) && is_string($filterData['post_id']) && $filterData['post_id'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['post_id'] ?? null) === $filterData['post_id']
            ));
        }

        if (isset($filterData['code']) && is_string($filterData['code']) && $filterData['code'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['code'] ?? null) === $filterData['code']
            ));
        }

        if (isset($filterData['key']) && is_string($filterData['key']) && $filterData['key'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['key'] ?? null) === $filterData['key']
            ));
        }

        if (isset($filterData['type']) && is_string($filterData['type']) && $filterData['type'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['type'] ?? null) === $filterData['type']
            ));
        }

        return $files;
    }
}
