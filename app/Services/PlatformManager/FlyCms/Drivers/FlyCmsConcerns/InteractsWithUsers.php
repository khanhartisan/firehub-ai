<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\UserFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\CreateUserData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\UpdateUserData;
use App\Contracts\PlatformManager\FlyCms\Resources\UserResource;

trait InteractsWithUsers
{
    /**
     * @throws FlyCmsException
     */
    public function showUser(string $userId): ?UserResource
    {
        /** @var ?UserResource */
        return $this->showResource(UserResource::class, $userId);
    }

    /**
     * @throws FlyCmsException
     */
    public function createUser(CreateUserData $createUserData): UserResource
    {
        /** @var UserResource */
        return $this->createResource(
            UserResource::class,
            $createUserData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function updateUser(string $userId, UpdateUserData $updateUserData): UserResource
    {
        /** @var UserResource */
        return $this->updateResource(
            UserResource::class,
            $userId,
            $updateUserData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function listUsers(int $page = 1, int $limit = 100, ?UserFilter $userFilter = null): array
    {
        return $this->listResources(
            UserResource::class,
            $page,
            $limit,
            null,
            $userFilter
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteUser(string $userId): bool
    {
        return $this->deleteResource(UserResource::class, $userId);
    }
}
