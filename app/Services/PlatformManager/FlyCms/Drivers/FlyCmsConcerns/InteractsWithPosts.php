<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\PostFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\CreatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\FlyCms\Resources\PostResource;

trait InteractsWithPosts
{
    /**
     * @throws FlyCmsException
     */
    public function showPost(string $postId): ?PostResource
    {
        /** @var ?PostResource */
        return $this->showResource(PostResource::class, $postId);
    }

    /**
     * @throws FlyCmsException
     */
    public function createPost(CreatePostData $createPostData): PostResource
    {
        $response = $this->sendApiRequest('POST', PostResource::resourceNamespace().':composite', [
            'json' => $createPostData->toArray()['data'],
        ]);

        if (! $data = $this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to create post (Unknown error)');
        }

        return PostResource::fromArray($data);
    }

    /**
     * @throws FlyCmsException
     */
    public function updatePost(UpdatePostData $updatePostData): PostResource
    {
        $data = $updatePostData->getData() ?? [];
        $postId = $data['id'] ?? null;

        if (! is_string($postId) || $postId === '') {
            throw new FlyCmsException('Post id is required for update.');
        }

        unset($data['id']);

        $response = $this->sendApiRequest('PATCH', PostResource::resourceNamespace().'/'.$postId.':composite', [
            'json' => $data,
        ]);

        if (! $data = $this->parseResponseData($response)) {
            throw new FlyCmsException('Failed to update post (Unknown error)');
        }

        return PostResource::fromArray($data);
    }

    /**
     * @throws FlyCmsException
     */
    public function listPosts(string $websiteId,
                              int $page = 1,
                              int $limit = 100,
                              ?int $orderDirection = null,
                              ?PostFilter $postFilter = null): array
    {
        $filterData = array_merge(
            ['website_id' => $websiteId],
            $postFilter?->getFilterData() ?? []
        );

        return $this->listResources(
            PostResource::class,
            $page,
            $limit,
            $this->resolvePostSort($orderDirection),
            (new PostFilter)->setFilterData($filterData)
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deletePost(string $postId): bool
    {
        return $this->deleteResource(PostResource::class, $postId);
    }

    protected function resolvePostSort(?int $orderDirection): ?string
    {
        if ($orderDirection === null) {
            return null;
        }

        return $orderDirection === -1 ? '-id' : 'id';
    }
}
