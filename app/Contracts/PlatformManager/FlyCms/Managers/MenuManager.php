<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;

interface MenuManager
{
    public function showMenu(string $menuId): ?MenuResource;

    public function createMenu(CreateMenuData $createMenuData): MenuResource;

    public function updateMenu(string $menuId, UpdateMenuData $updateMenuData): MenuResource;

    /**
     * @param string $websiteId
     * @return MenuResource[]
     */
    public function listMenus(string $websiteId): array;

    public function deleteMenu(string $menuId): bool;
}
