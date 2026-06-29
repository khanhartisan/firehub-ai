<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\AuthorFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\AuthorMutationData\PutAuthorData;
use App\Contracts\PlatformManager\FlyCms\Resources\AuthorResource;

trait InteractsWithAuthors
{
    /**
     * @throws FlyCmsException
     */
    public function showAuthor(string $websiteId, string $email): ?AuthorResource
    {
        return $this->listAuthors(
            $websiteId,
            1,
            1,
            new AuthorFilter()
                ->setFilterData([
                    'email' => $email,
                ])
        )[0] ?? null;
    }

    /**
     * @throws FlyCmsException
     */
    public function putAuthor(string $websiteId, PutAuthorData $putAuthorData): AuthorResource
    {
        // TODO: Implement putAuthor via FlyCMS API
        throw new FlyCmsException('Not implemented');
    }

    /**
     * @return AuthorResource[]
     *
     * @throws FlyCmsException
     */
    public function listAuthors(string $websiteId,
                                int $page = 1,
                                int $perPage = 100,
                                ?AuthorFilter $authorFilter = null): array
    {
        // TODO: Implement listAuthors via FlyCMS API
        throw new FlyCmsException('Not implemented');
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteAuthor(string $websiteId, string $email): bool
    {
        // TODO: Implement deleteAuthor via FlyCMS API
        throw new FlyCmsException('Not implemented');
    }
}
