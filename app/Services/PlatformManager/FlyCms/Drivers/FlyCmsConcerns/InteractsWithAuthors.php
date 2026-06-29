<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\AuthorFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\UserFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\AuthorMutationData\PutAuthorData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\CreateUserData;
use App\Contracts\PlatformManager\FlyCms\Resources\AuthorResource;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;
use App\Utils\Str;

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
        if (!$email = $putAuthorData->get('email')) {
            throw new FlyCmsException('email is required');
        }

        // Attach user to the website
        if (!$authorResource = $this->showAuthor($websiteId, $email)
            or !$authorId = $authorResource->get('id')
        ) {

            // Create the user if not found
            if (!$userResource = $this
                    ->listUsers(1, 1, new UserFilter()->setFilterData([
                        'email' => $email,
                    ]))[0] ?? null
            ) {
                $userResource = $this
                    ->createUser(new CreateUserData()->setData([
                        'name' => $email,
                        'email' => $email,
                        'password' => Str::random()
                    ]));
            }

            $attachResponse = $this->sendApiRequest(
                'POST',
                WebsiteResource::resourceNamespace().':add_user',
                [
                    'json' => [
                        'user_id' => $userResource->get('id')
                    ]
                ]
            );

            if (!$attachData = $this->parseResponseData($attachResponse)
                or !$authorId = $attachData['id'] ?? null
            ) {
                throw new FlyCmsException('Failed to create author (unknown error)');
            }
        }

        /** @var AuthorResource */
        return $this->updateResource(
            AuthorResource::class,
            $authorId,
            $putAuthorData
        );
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
        $authorFilter = $authorFilter ?? new AuthorFilter();
        $authorFilter->set('website_id', $websiteId);

        return $this->listResources(
            AuthorResource::class,
            $page,
            $perPage,
            null,
            $authorFilter
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteAuthor(string $websiteId, string $email): bool
    {
        $userResource = $this
            ->listUsers(1, 1, new UserFilter()->setFilterData([
                'email' => $email,
            ]))[0] ?? null;

        $this->sendApiRequest(
            'POST',
            WebsiteResource::resourceNamespace().':remove_user',
            [
                'json' => [
                    'user_id' => $userResource->get('id')
                ]
            ]
        );

        return true;
    }
}
