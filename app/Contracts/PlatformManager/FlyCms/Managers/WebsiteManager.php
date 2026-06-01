<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;

interface WebsiteManager
{
    public function showWebsite(string $websiteId): ?WebsiteResource;

    public function createWebsite(CreateWebsiteData $createWebsiteData): WebsiteResource;

    public function updateWebsite(string $websiteId, UpdateWebsiteData $updateWebsiteData): WebsiteResource;

    /**
     * Get a list of websites
     *
     * @param int $page
     * @param int $limit
     * @param WebsiteFilter|null $websiteFilter
     * @return WebsiteResource[]
     */
    public function listWebsites(int $page = 1, int $limit = 100, ?WebsiteFilter $websiteFilter = null): array;

    public function deleteWebsite(string $websiteId): bool;
}
