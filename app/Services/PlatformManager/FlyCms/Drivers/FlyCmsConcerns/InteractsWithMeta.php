<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\MetaFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\MetaMutationData\PutMetaData;
use App\Contracts\PlatformManager\FlyCms\Resources\MetaResource;

trait InteractsWithMeta
{
    /**
     * @return MetaResource[]
     *
     * @throws FlyCmsException
     */
    public function listMeta(string $metableType,
                             string $metableId,
                             int $page = 1,
                             int $limit = 100,
                             ?MetaFilter $metaFilter = null): array
    {
        $filterData = array_merge(
            [
                'metable_type' => $metableType,
                'metable_id' => $metableId,
            ],
            ($metaFilter ?? new MetaFilter)->getFilterData()
        );

        return $this->listResources(
            MetaResource::class,
            $page,
            $limit,
            null,
            (new MetaFilter)->setFilterData($filterData)
        );
    }

    /**
     * @return MetaResource[]
     *
     * @throws FlyCmsException
     */
    public function putMeta(PutMetaData $putMetaData): array
    {
        $response = $this->sendApiRequest('PUT', MetaResource::resourceNamespace().':many', [
            'json' => $putMetaData->toArray()['data'],
        ]);

        if (! $data = $this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to put meta (Unknown error)');
        }

        $records = array_is_list($data) ? $data : [$data];

        return array_map(
            static fn (array $resourceData): MetaResource => MetaResource::fromArray($resourceData),
            $records
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteMeta(string $metaId): bool
    {
        return $this->deleteResource(MetaResource::class, $metaId);
    }
}
