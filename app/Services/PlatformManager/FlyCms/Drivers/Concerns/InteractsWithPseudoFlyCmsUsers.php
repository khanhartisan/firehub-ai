<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\Concerns;

use App\Contracts\PlatformManager\FlyCms\Filters\UserFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\CreateUserData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\UpdateUserData;
use App\Contracts\PlatformManager\FlyCms\Resources\UserResource;
use Illuminate\Support\Str;

trait InteractsWithPseudoFlyCmsUsers
{
    public function showUser(string $userId): ?UserResource
    {
        $user = self::$users[$userId] ?? null;

        if ($user === null) {
            return null;
        }

        return $this->toUserResource($user);
    }

    public function createUser(CreateUserData $createUserData): UserResource
    {
        $userId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createUserData->getData() ?? [];
        unset($data['password']);

        $user = array_merge($this->defaultUserAttributes(), $data, [
            'id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::$users[$userId] = $user;

        return $this->toUserResource($user);
    }

    public function updateUser(string $userId, UpdateUserData $updateUserData): UserResource
    {
        $user = self::$users[$userId] ?? null;

        if ($user === null) {
            throw new \InvalidArgumentException("User [{$userId}] not found.");
        }

        $data = array_filter(
            $updateUserData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );
        unset($data['password']);

        $user = array_merge($user, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        self::$users[$userId] = $user;

        return $this->toUserResource($user);
    }

    /**
     * @return UserResource[]
     */
    public function listUsers(int $page = 1, int $limit = 100, ?UserFilter $userFilter = null): array
    {
        $users = array_values(self::$users);

        if ($userFilter !== null) {
            $users = $this->applyUserFilter($users, $userFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $users = array_slice($users, $offset, $limit);

        return array_map(
            fn (array $user): UserResource => $this->toUserResource($user),
            $users
        );
    }

    public function deleteUser(string $userId): bool
    {
        if (! isset(self::$users[$userId])) {
            return false;
        }

        unset(self::$users[$userId]);

        return true;
    }
    protected function seedSampleUsers(): void
    {
        $now = now()->toIso8601String();

        self::$users = [
            '01J00000000000000000000061' => array_merge($this->defaultUserAttributes(), [
                'id' => '01J00000000000000000000061',
                'role_id' => '01J00000000000000000000091',
                'name' => 'Alex Editor',
                'email' => 'alex@example.com',
                'website_ids' => ['01J00000000000000000000001'],
                'branch_ids' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000062' => array_merge($this->defaultUserAttributes(), [
                'id' => '01J00000000000000000000062',
                'role_id' => '01J00000000000000000000092',
                'name' => 'Sam Manager',
                'email' => 'sam@example.com',
                'level' => 5,
                'website_ids' => ['01J00000000000000000000001', '01J00000000000000000000002'],
                'branch_ids' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultUserAttributes(): array
    {
        return [
            'role_id' => null,
            'is_super' => false,
            'name' => 'Untitled User',
            'email' => 'user@example.com',
            'email_verified_at' => null,
            'level' => 0,
            'balance' => 0.0,
            'api_key' => null,
            'log_api_methods' => null,
            'api_logs_limit' => 10000,
            'website_ids' => [],
            'branch_ids' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function userRecordForOutput(array $user): array
    {
        unset($user['website_ids'], $user['branch_ids'], $user['password']);

        return $user;
    }

    protected function toUserResource(array $user): UserResource
    {
        return new UserResource($this->userRecordForOutput($user));
    }
    protected function applyUserFilter(array $users, UserFilter $userFilter): array
    {
        $filterData = $userFilter->getFilterData();

        if (isset($filterData['ids']) && is_string($filterData['ids']) && $filterData['ids'] !== '') {
            $ids = array_map('trim', explode(',', $filterData['ids']));
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool => in_array($user['id'] ?? null, $ids, true)
            ));
        }

        if (isset($filterData['search']) && is_string($filterData['search']) && $filterData['search'] !== '') {
            $search = strtolower($filterData['search']);
            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($search): bool {
                    $name = strtolower((string) ($user['name'] ?? ''));

                    return str_contains($name, $search);
                }
            ));
        }

        if (isset($filterData['website_id']) && is_string($filterData['website_id']) && $filterData['website_id'] !== '') {
            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($filterData): bool {
                    $websiteIds = $user['website_ids'] ?? [];

                    return is_array($websiteIds) && in_array($filterData['website_id'], $websiteIds, true);
                }
            ));
        }

        if (isset($filterData['branch_id']) && is_string($filterData['branch_id']) && $filterData['branch_id'] !== '') {
            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($filterData): bool {
                    $branchIds = $user['branch_ids'] ?? [];

                    return is_array($branchIds) && in_array($filterData['branch_id'], $branchIds, true);
                }
            ));
        }

        if (isset($filterData['role_id']) && is_string($filterData['role_id']) && $filterData['role_id'] !== '') {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool => ($user['role_id'] ?? null) === $filterData['role_id']
            ));
        }

        return $users;
    }
}
