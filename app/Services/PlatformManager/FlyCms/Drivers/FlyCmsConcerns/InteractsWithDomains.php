<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\DomainFilter;
use App\Contracts\PlatformManager\FlyCms\Resources\DomainResource;

trait InteractsWithDomains
{
    /**
     * @throws FlyCmsException
     */
    public function showDomain(string $domainId): ?DomainResource
    {
        /** @var ?DomainResource */
        return $this->showResource(DomainResource::class, $domainId);
    }

    /**
     * @throws FlyCmsException
     */
    public function listDomains(int           $page = 1,
                                int           $limit = 100,
                                ?DomainFilter $domainFilter = null): array
    {
        return $this->listResources(
            DomainResource::class,
            $page,
            $limit,
            null,
            $domainFilter
        );
    }
}