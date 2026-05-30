<?php

namespace App\Contracts\Platforms\FlyCms\Managers;

use App\Contracts\Platforms\FlyCms\Filters\WebsiteFilter;
use App\Contracts\Platforms\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\Platforms\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Contracts\Platforms\FlyCms\Resources\WebsiteResource;

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
