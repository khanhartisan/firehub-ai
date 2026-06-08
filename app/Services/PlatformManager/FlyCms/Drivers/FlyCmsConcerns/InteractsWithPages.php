<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\PageFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\CreatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\UpdatePageData;
use App\Contracts\PlatformManager\FlyCms\Resources\PageResource;

trait InteractsWithPages
{
    /**
     * @throws FlyCmsException
     */
    public function showPage(string $pageId): ?PageResource
    {
        /** @var ?PageResource */
        return $this->showResource(PageResource::class, $pageId);
    }

    /**
     * @throws FlyCmsException
     */
    public function createPage(CreatePageData $createPageData): PageResource
    {
        /** @var PageResource */
        return $this->createResource(
            PageResource::class,
            $createPageData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function updatePage(string $pageId, UpdatePageData $updatePageData): PageResource
    {
        /** @var PageResource */
        return $this->updateResource(
            PageResource::class,
            $pageId,
            $updatePageData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function listPages(string $websiteId, int $page = 1, int $limit = 100): array
    {
        return $this->listResources(
            PageResource::class,
            $page,
            $limit,
            null,
            (new PageFilter)->setFilterData(['website_id' => $websiteId])
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deletePage(string $pageId): void
    {
        $this->deleteResource(PageResource::class, $pageId);
    }
}
