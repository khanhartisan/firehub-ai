<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\DomainFilter;
use App\Contracts\PlatformManager\FlyCms\Resources\DomainResource;

interface DomainManager
{
    public function showDomain(string $domainId): ?DomainResource;

    public function listDomains(int $page = 1,
                                int $limit = 100,
                                ?DomainFilter $domainFilter = null): array;
}
