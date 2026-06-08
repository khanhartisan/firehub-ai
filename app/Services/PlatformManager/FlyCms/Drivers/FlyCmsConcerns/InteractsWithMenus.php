<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\MenuFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;

trait InteractsWithMenus
{
    /**
     * @throws FlyCmsException
     */
    public function showMenu(string $menuId): ?MenuResource
    {
        /** @var ?MenuResource */
        return $this->showResource(MenuResource::class, $menuId);
    }

    /**
     * @throws FlyCmsException
     */
    public function createMenu(CreateMenuData $createMenuData): MenuResource
    {
        /** @var MenuResource */
        return $this->createResource(
            MenuResource::class,
            $createMenuData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function updateMenu(string $menuId, UpdateMenuData $updateMenuData): MenuResource
    {
        /** @var MenuResource */
        return $this->updateResource(
            MenuResource::class,
            $menuId,
            $updateMenuData
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function listMenus(string $websiteId): array
    {
        return $this->listResources(
            MenuResource::class,
            1,
            1000,
            null,
            (new MenuFilter)->setFilterData(['website_id' => $websiteId])
        );
    }

    /**
     * @throws FlyCmsException
     */
    public function deleteMenu(string $menuId): bool
    {
        return $this->deleteResource(
            MenuResource::class,
            $menuId
        );
    }
}