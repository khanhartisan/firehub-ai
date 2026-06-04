<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\PostFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\CreatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\FlyCms\Resources\PostResource;

interface PostManager
{
    public function showPost(string $postId): ?PostResource;

    public function createPost(CreatePostData $createPostData): PostResource;

    public function updatePost(UpdatePostData $updatePostData): PostResource;

    /**
     * @param string $websiteId
     * @param int $page
     * @param int $limit
     * @param ?int $orderDirection null: default, -1: newer first, 1: older first
     * @param PostFilter|null $postFilter
     * @return PostResource[]
     */
    public function listPosts(string $websiteId,
                              int $page = 1,
                              int $limit = 100,
                              ?int $orderDirection = null,
                              ?PostFilter $postFilter = null): array;

    public function deletePost(string $postId): bool;
}
