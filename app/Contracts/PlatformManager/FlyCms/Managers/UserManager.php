<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\UserFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\CreateUserData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\UpdateUserData;
use App\Contracts\PlatformManager\FlyCms\Resources\UserResource;

interface UserManager
{
    public function showUser(string $userId): ?UserResource;

    public function createUser(CreateUserData $createUserData): UserResource;

    public function updateUser(string $userId, UpdateUserData $updateUserData): UserResource;

    /**
     * @param int $page
     * @param int $limit
     * @param UserFilter|null $userFilter
     * @return UserResource[]
     */
    public function listUsers(int $page = 1,
                              int $limit = 100,
                              ?UserFilter $userFilter = null): array;

    public function deleteUser(string $userId): bool;
}
