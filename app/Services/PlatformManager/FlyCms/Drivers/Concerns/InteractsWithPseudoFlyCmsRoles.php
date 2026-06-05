<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\Concerns;

use App\Contracts\PlatformManager\FlyCms\Filters\RoleFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\CreateRoleData;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\UpdateRoleData;
use App\Contracts\PlatformManager\FlyCms\Resources\RoleResource;
use Illuminate\Support\Str;

trait InteractsWithPseudoFlyCmsRoles
{
    public function showRole(string $roleId): ?RoleResource
    {
        $role = self::$roles[$roleId] ?? null;

        if ($role === null) {
            return null;
        }

        return new RoleResource($role);
    }

    public function createRole(CreateRoleData $createRoleData): RoleResource
    {
        $roleId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createRoleData->getData() ?? [];

        $role = array_merge($this->defaultRoleAttributes(), $data, [
            'id' => $roleId,
            'abilities' => is_array($data['abilities'] ?? null) ? $data['abilities'] : [],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::$roles[$roleId] = $role;

        return new RoleResource($role);
    }

    public function updateRole(string $roleId, UpdateRoleData $updateRoleData): RoleResource
    {
        $role = self::$roles[$roleId] ?? null;

        if ($role === null) {
            throw new \InvalidArgumentException("Role [{$roleId}] not found.");
        }

        $data = array_filter(
            $updateRoleData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        $role = array_merge($role, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        self::$roles[$roleId] = $role;

        return new RoleResource($role);
    }

    /**
     * @return RoleResource[]
     */
    public function listRoles(int $page = 1, int $perPage = 100, ?RoleFilter $roleFilter = null): array
    {
        $roles = array_values(self::$roles);

        if ($roleFilter !== null) {
            $roles = $this->applyRoleFilter($roles, $roleFilter);
        }

        $offset = max(0, ($page - 1) * $perPage);
        $roles = array_slice($roles, $offset, $perPage);

        return array_map(
            static fn (array $role): RoleResource => new RoleResource($role),
            $roles
        );
    }

    public function deleteRole(string $roleId): RoleResource
    {
        $role = self::$roles[$roleId] ?? null;

        if ($role === null) {
            throw new \InvalidArgumentException("Role [{$roleId}] not found.");
        }

        $resource = new RoleResource($role);

        unset(self::$roles[$roleId]);

        return $resource;
    }
    protected function seedSampleRoles(): void
    {
        $now = now()->toIso8601String();

        self::$roles = [
            '01J00000000000000000000091' => array_merge($this->defaultRoleAttributes(), [
                'id' => '01J00000000000000000000091',
                'name' => 'Editor',
                'abilities' => ['posts.create', 'posts.update', 'posts.delete'],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000092' => array_merge($this->defaultRoleAttributes(), [
                'id' => '01J00000000000000000000092',
                'name' => 'Manager',
                'abilities' => ['posts.create', 'posts.update', 'posts.delete', 'users.manage'],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultRoleAttributes(): array
    {
        return [
            'name' => 'Untitled Role',
            'abilities' => [],
        ];
    }
    protected function applyRoleFilter(array $roles, RoleFilter $roleFilter): array
    {
        $filterData = $roleFilter->getFilterData();

        if (isset($filterData['search']) && is_string($filterData['search']) && $filterData['search'] !== '') {
            $search = strtolower($filterData['search']);
            $roles = array_values(array_filter(
                $roles,
                static function (array $role) use ($search): bool {
                    $name = strtolower((string) ($role['name'] ?? ''));

                    return str_contains($name, $search);
                }
            ));
        }

        return $roles;
    }
}
