<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\RoleFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\CreateRoleData;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\UpdateRoleData;
use App\Contracts\PlatformManager\FlyCms\Resources\RoleResource;

interface RoleManager
{
    public function showRole(string $roleId): ?RoleResource;

    public function createRole(CreateRoleData $createRoleData): RoleResource;

    public function updateRole(string $roleId, UpdateRoleData $updateRoleData): RoleResource;

    /**
     * @param int $page
     * @param int $perPage
     * @param RoleFilter|null $roleFilter
     * @return RoleResource[]
     */
    public function listRoles(int $page = 1, int $perPage = 100, ?RoleFilter $roleFilter = null): array;

    public function deleteRole(string $roleId): RoleResource;
}