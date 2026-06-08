<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;

trait InteractsWithWebsites
{
    /**
     * @throws FlyCmsException
     */
    public function showWebsite(string $websiteId): ?WebsiteResource
    {
        /** @var ?WebsiteResource */
        return $this->showResource(WebsiteResource::class, $websiteId);
    }

    /**
     * @throws FlyCmsException
     */
    public function createWebsite(CreateWebsiteData $createWebsiteData): WebsiteResource
    {
        /** @var WebsiteResource */
        return $this->createResource(
            WebsiteResource::class,
            $createWebsiteData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function updateWebsite(string $websiteId, UpdateWebsiteData $updateWebsiteData): WebsiteResource
    {
        /** @var WebsiteResource */
        return $this->updateResource(
            WebsiteResource::class,
            $websiteId,
            $updateWebsiteData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function listWebsites(int $page = 1, int $limit = 100, ?WebsiteFilter $websiteFilter = null): array
    {
        return $this->listResources(
            WebsiteResource::class,
            $page,
            $limit,
            null,
            $websiteFilter
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteWebsite(string $websiteId): bool
    {
        return $this->deleteResource(WebsiteResource::class, $websiteId);
    }
}
