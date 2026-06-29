<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\AuthorFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\AuthorMutationData\PutAuthorData;
use App\Contracts\PlatformManager\FlyCms\Resources\AuthorResource;

interface AuthorManager
{
    public function showAuthor(string $websiteId, string $email): ?AuthorResource;

    public function putAuthor(string $websiteId,
                              PutAuthorData $putAuthorData): AuthorResource;

    /**
     * @param string $websiteId
     * @param int $page
     * @param int $perPage
     * @param AuthorFilter|null $authorFilter
     * @return AuthorResource[]
     */
    public function listAuthors(string $websiteId,
                                int $page = 1,
                                int $perPage = 100,
                                ?AuthorFilter $authorFilter = null): array;

    public function deleteAuthor(string $websiteId, string $email): bool;
}