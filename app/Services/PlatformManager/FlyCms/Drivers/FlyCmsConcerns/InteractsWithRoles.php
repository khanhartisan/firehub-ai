<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\RoleFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\CreateRoleData;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\UpdateRoleData;
use App\Contracts\PlatformManager\FlyCms\Resources\RoleResource;

trait InteractsWithRoles
{
    /**
     * @throws FlyCmsException
     */
    public function showRole(string $roleId): ?RoleResource
    {
        /** @var ?RoleResource */
        return $this->showResource(RoleResource::class, $roleId);
    }

    /**
     * @throws FlyCmsException
     */
    public function createRole(CreateRoleData $createRoleData): RoleResource
    {
        /** @var RoleResource */
        return $this->createResource(
            RoleResource::class,
            $createRoleData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function updateRole(string $roleId, UpdateRoleData $updateRoleData): RoleResource
    {
        /** @var RoleResource */
        return $this->updateResource(
            RoleResource::class,
            $roleId,
            $updateRoleData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function listRoles(int $page = 1, int $perPage = 100, ?RoleFilter $roleFilter = null): array
    {
        return $this->listResources(
            RoleResource::class,
            $page,
            $perPage,
            null,
            $roleFilter
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteRole(string $roleId): RoleResource
    {
        $role = $this->showRole($roleId);

        if ($role === null) {
            throw new FlyCmsException("Role [{$roleId}] not found.");
        }

        $this->deleteResource(RoleResource::class, $roleId);

        return $role;
    }
}
