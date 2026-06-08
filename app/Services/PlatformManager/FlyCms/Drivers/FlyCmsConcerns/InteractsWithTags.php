<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;

trait InteractsWithTags
{
    /**
     * @throws FlyCmsException
     */
    public function showTag(string $tagId): ?TagResource
    {
        /** @var ?TagResource */
        return $this->showResource(TagResource::class, $tagId);
    }

    /**
     * @throws FlyCmsException
     */
    public function createTag(CreateTagData $createTagData): TagResource
    {
        /** @var TagResource */
        return $this->createResource(
            TagResource::class,
            $createTagData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function updateTag(string $tagId, UpdateTagData $updateTagData): TagResource
    {
        /** @var TagResource */
        return $this->updateResource(
            TagResource::class,
            $tagId,
            $updateTagData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function listTags(string $websiteId,
                             int $page = 1,
                             int $limit = 100,
                             ?TagFilter $tagFilter = null): array
    {
        $filterData = array_merge(
            ['website_id' => $websiteId],
            ($tagFilter ?? new TagFilter)->getFilterData()
        );

        return $this->listResources(
            TagResource::class,
            $page,
            $limit,
            null,
            (new TagFilter)->setFilterData($filterData)
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteTag(string $tagId): bool
    {
        return $this->deleteResource(TagResource::class, $tagId);
    }
}
