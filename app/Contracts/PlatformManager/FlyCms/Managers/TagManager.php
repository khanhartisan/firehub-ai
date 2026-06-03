<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;

interface TagManager
{
    public function showTag(string $tagId): ?TagResource;

    public function createTag(CreateTagData $createTagData): TagResource;

    public function updateTag(string $tagId, UpdateTagData $updateTagData): TagResource;

    /**
     * @param string $websiteId
     * @param int $page
     * @param int $limit
     * @param TagFilter|null $tagFilter
     * @return TagResource[]
     */
    public function listTags(string $websiteId,
                             int $page = 1,
                             int $limit = 100,
                             ?TagFilter $tagFilter = null): array;

    public function deleteTag(string $tagId): bool;
}
