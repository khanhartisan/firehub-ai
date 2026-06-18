<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\BaseTagFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\BaseTagMutationData\CreateBaseTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use App\Http\Resources\BaseTagResource;

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
        $baseTagName = $createTagData->get('name');
        $baseTag = $this->ensureBaseTag($baseTagName);

        $existing = $this->listTags(
            $createTagData->get('website_id'),
            1,
            1,
            new TagFilter()->setFilterData([
                'tag_id' => $baseTag->get('id'),
            ])
        )[0] ?? null;

        // Update if already exists
        if ($existing) {
            return $this->updateTag(
                $existing->get('id'),
                new UpdateTagData()->setData($createTagData->getData())
            );
        }

        // Otherwise create new
        $createTagData = $createTagData->getData();
        $createTagData['name'] = $createTagData['display_name'];
        unset($createTagData['display_name']);
        $response = $this->sendApiRequest('POST', TagResource::resourceNamespace(), [
            'json' => $createTagData
        ]);

        if (!$data = $this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to create resource (Unknown error)');
        }

        return TagResource::fromArray($data);
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
    protected function ensureBaseTag(string $name): BaseTagResource
    {
        $existingBaseTag = $this->listResources(
            BaseTagResource::class,
            1,
            1,
            null,
            new BaseTagFilter()->setFilterData([
                'name' => $name,
            ])
        );

        if (!$existingBaseTag) {
            /** @var BaseTagResource */
            return $this->createResource(
                BaseTagResource::class,
                new CreateBaseTagData()->setData([
                    'name' => $name,
                ])
            );
        }

        /** @var BaseTagResource */
        return $existingBaseTag[0];
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
