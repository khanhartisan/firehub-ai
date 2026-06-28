<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\MutationData\AuthorMutationData\PutAuthorData;
use App\Contracts\PlatformManager\FlyCms\Resources\AuthorResource;

interface AuthorManager
{
    public function showAuthor(string $authorId);

    public function putAuthor(string $websiteId,
                              PutAuthorData $putAuthorData): AuthorResource;

    /**
     * @param string $websiteId
     * @return AuthorResource[]
     */
    public function listAuthors(string $websiteId): array;

    public function deleteAuthor(string $authorId): bool;
}