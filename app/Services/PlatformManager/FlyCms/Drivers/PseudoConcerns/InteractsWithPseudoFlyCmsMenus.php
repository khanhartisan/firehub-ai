<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns;

use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use Illuminate\Support\Str;

trait InteractsWithPseudoFlyCmsMenus
{
    public function showMenu(string $menuId): ?MenuResource
    {
        $menu = self::$menus[$menuId] ?? null;

        if ($menu === null) {
            return null;
        }

        return new MenuResource($menu);
    }

    public function createMenu(CreateMenuData $createMenuData): MenuResource
    {
        $menuId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createMenuData->getData() ?? [];

        $menu = array_merge($this->defaultMenuAttributes(), $data, [
            'id' => $menuId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::$menus[$menuId] = $menu;

        return new MenuResource($menu);
    }

    public function updateMenu(string $menuId, UpdateMenuData $updateMenuData): MenuResource
    {
        $menu = self::$menus[$menuId] ?? null;

        if ($menu === null) {
            throw new \InvalidArgumentException("Menu [{$menuId}] not found.");
        }

        $data = array_filter(
            $updateMenuData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        $menu = array_merge($menu, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        self::$menus[$menuId] = $menu;

        return new MenuResource($menu);
    }

    /**
     * @return MenuResource[]
     */
    public function listMenus(string $websiteId): array
    {
        $menus = array_values(array_filter(
            self::$menus,
            static fn (array $menu): bool => ($menu['website_id'] ?? null) === $websiteId
        ));

        return array_map(
            static fn (array $menu): MenuResource => new MenuResource($menu),
            $menus
        );
    }

    public function deleteMenu(string $menuId): bool
    {
        if (! isset(self::$menus[$menuId])) {
            return false;
        }

        unset(self::$menus[$menuId]);

        return true;
    }
    protected function seedSampleMenus(): void
    {
        $now = now()->toIso8601String();

        self::$menus = [
            '01J00000000000000000000011' => array_merge($this->defaultMenuAttributes(), [
                'id' => '01J00000000000000000000011',
                'website_id' => '01J00000000000000000000001',
                'key' => 'main',
                'items' => [
                    [
                        'text' => 'Home',
                        'link' => '/',
                        'new_tab' => 0,
                    ],
                    [
                        'text' => 'About',
                        'link' => '/about',
                        'new_tab' => 0,
                    ],
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000012' => array_merge($this->defaultMenuAttributes(), [
                'id' => '01J00000000000000000000012',
                'website_id' => '01J00000000000000000000001',
                'key' => 'footer',
                'items' => [
                    [
                        'text' => 'Privacy',
                        'link' => '/privacy',
                        'new_tab' => 0,
                    ],
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000013' => array_merge($this->defaultMenuAttributes(), [
                'id' => '01J00000000000000000000013',
                'website_id' => '01J00000000000000000000002',
                'key' => 'main',
                'items' => [
                    [
                        'text' => 'Shop',
                        'link' => '/shop',
                        'new_tab' => 0,
                    ],
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }
    protected function defaultMenuAttributes(): array
    {
        return [
            'website_id' => null,
            'key' => 'main',
            'items' => [],
        ];
    }
}
