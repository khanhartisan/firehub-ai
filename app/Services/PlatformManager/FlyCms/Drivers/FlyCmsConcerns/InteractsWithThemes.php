<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\ThemeFilter;
use App\Contracts\PlatformManager\FlyCms\Resources\ThemeResource;

trait InteractsWithThemes
{
    /**
     * @throws FlyCmsException
     */
    public function showTheme(string $themeId): ?ThemeResource
    {
        /** @var ?ThemeResource */
        return $this->showResource(ThemeResource::class, $themeId);
    }

    /**
     * @throws FlyCmsException
     */
    public function listThemes(int $page = 1, int $limit = 100, ?ThemeFilter $themeFilter = null): array
    {
        return $this->listResources(
            ThemeResource::class,
            $page,
            $limit,
            null,
            $themeFilter
        );
    }
}
