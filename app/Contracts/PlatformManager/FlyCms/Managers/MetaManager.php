<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\MetaFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\MetaMutationData\PutMetaData;
use App\Contracts\PlatformManager\FlyCms\Resources\MetaResource;

interface MetaManager
{
    /**
     * @return MetaResource[]
     */
    public function listMeta(string $metableType,
                             string $metableId,
                             int $page = 1,
                             int $limit = 100,
                             ?MetaFilter $metaFilter = null): array;

    /**
     * @return MetaResource[]
     */
    public function putMeta(PutMetaData $putMetaData): array;

    public function deleteMeta(string $metaId): bool;
}
