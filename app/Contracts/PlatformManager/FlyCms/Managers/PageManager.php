<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\CreatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\UpdatePageData;
use App\Contracts\PlatformManager\FlyCms\Resources\PageResource;

interface PageManager
{
    public function showPage(string $pageId): ?PageResource;

    public function createPage(CreatePageData $createPageData): PageResource;

    public function updatePage(string $pageId, UpdatePageData $updatePageData): PageResource;

    /**
     * @param string $websiteId
     * @param int $page
     * @param int $limit
     * @return PageResource[]
     */
    public function listPages(string $websiteId, int $page = 1, int $limit = 100): array;

    public function deletePage(string $pageId): void;
}
