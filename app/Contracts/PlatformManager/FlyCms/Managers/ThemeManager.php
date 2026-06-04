<?php

namespace App\Contracts\PlatformManager\FlyCms\Managers;

use App\Contracts\PlatformManager\FlyCms\Filters\ThemeFilter;
use App\Contracts\PlatformManager\FlyCms\Resources\ThemeResource;

interface ThemeManager
{
    public function showTheme(string $themeId): ?ThemeResource;

    /**
     * @param int $page
     * @param int $limit
     * @param ThemeFilter|null $themeFilter
     * @return ThemeResource[]
     */
    public function listThemes(int $page = 1,
                               int $limit = 100,
                               ?ThemeFilter $themeFilter = null): array;
}
