<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\Concerns;

use App\Contracts\PlatformManager\FlyCms\Filters\ThemeFilter;
use App\Contracts\PlatformManager\FlyCms\Resources\ThemeResource;

trait InteractsWithPseudoFlyCmsThemes
{
    public function showTheme(string $themeId): ?ThemeResource
    {
        $theme = self::$themes[$themeId] ?? null;

        if ($theme === null) {
            return null;
        }

        return new ThemeResource($theme);
    }

    /**
     * @return ThemeResource[]
     */
    public function listThemes(int $page = 1, int $limit = 100, ?ThemeFilter $themeFilter = null): array
    {
        $themes = array_values(self::$themes);

        if ($themeFilter !== null) {
            $themes = $this->applyThemeFilter($themes, $themeFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $themes = array_slice($themes, $offset, $limit);

        return array_map(
            static fn (array $theme): ThemeResource => new ThemeResource($theme),
            $themes
        );
    }
    protected function seedSampleThemes(): void
    {
        $now = now()->toIso8601String();

        self::$themes = [
            '01J00000000000000000000081' => array_merge($this->defaultThemeAttributes(), [
                'id' => '01J00000000000000000000081',
                'name' => 'Good News',
                'description' => 'Blog theme with main and footer menu support.',
                'guidelines' => 'Use featured tags for homepage highlights. Default menu keys: main, footer.',
                'key' => 'goodnews',
                'dev_mode' => false,
                'websites_count' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000082' => array_merge($this->defaultThemeAttributes(), [
                'id' => '01J00000000000000000000082',
                'name' => 'Storefront',
                'description' => 'E-commerce oriented theme with extended menu keys.',
                'guidelines' => 'Supports main, footer, and shop menu keys.',
                'key' => 'storefront',
                'dev_mode' => true,
                'websites_count' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000083' => array_merge($this->defaultThemeAttributes(), [
                'id' => '01J00000000000000000000083',
                'name' => 'Minimal',
                'description' => null,
                'guidelines' => null,
                'key' => 'minimal',
                'dev_mode' => false,
                'websites_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }
    protected function defaultThemeAttributes(): array
    {
        return [
            'name' => 'Untitled Theme',
            'description' => null,
            'guidelines' => null,
            'key' => 'untitled-theme',
            'dev_mode' => false,
            'websites_count' => 0,
        ];
    }
    protected function applyThemeFilter(array $themes, ThemeFilter $themeFilter): array
    {
        $filterData = $themeFilter->getFilterData();

        if (isset($filterData['ids']) && is_string($filterData['ids']) && $filterData['ids'] !== '') {
            $ids = array_map('trim', explode(',', $filterData['ids']));
            $themes = array_values(array_filter(
                $themes,
                static fn (array $theme): bool => in_array($theme['id'] ?? null, $ids, true)
            ));
        }

        if (isset($filterData['search']) && is_string($filterData['search']) && $filterData['search'] !== '') {
            $search = strtolower($filterData['search']);
            $themes = array_values(array_filter(
                $themes,
                static function (array $theme) use ($search): bool {
                    $name = strtolower((string) ($theme['name'] ?? ''));

                    return str_contains($name, $search);
                }
            ));
        }

        return $themes;
    }
}
