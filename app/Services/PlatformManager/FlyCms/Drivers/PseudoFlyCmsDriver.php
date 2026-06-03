<?php

namespace App\Services\PlatformManager\FlyCms\Drivers;

use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;
use App\Services\PlatformManager\FlyCms\FlyCmsService;
use Illuminate\Support\Str;

class PseudoFlyCmsDriver extends FlyCmsService
{
    /** @var array<string, array<string, mixed>> */
    protected array $websites = [];

    /** @var array<string, array<string, mixed>> */
    protected array $menus = [];

    public function __construct()
    {
        $this->seedSampleWebsites();
        $this->seedSampleMenus();
    }

    public function showWebsite(string $websiteId): ?WebsiteResource
    {
        $website = $this->websites[$websiteId] ?? null;

        if ($website === null) {
            return null;
        }

        return new WebsiteResource($website);
    }

    public function createWebsite(CreateWebsiteData $createWebsiteData): WebsiteResource
    {
        $websiteId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createWebsiteData->getData() ?? [];

        $website = array_merge($this->defaultWebsiteAttributes(), $data, [
            'id' => $websiteId,
            'domains_count' => 0,
            'public_posts_count' => 0,
            'traffic_statistics' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'meta' => [],
        ]);

        $this->websites[$websiteId] = $website;

        return new WebsiteResource($website);
    }

    public function updateWebsite(string $websiteId, UpdateWebsiteData $updateWebsiteData): WebsiteResource
    {
        $website = $this->websites[$websiteId] ?? null;

        if ($website === null) {
            throw new \InvalidArgumentException("Website [{$websiteId}] not found.");
        }

        $data = array_filter(
            $updateWebsiteData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        $website = array_merge($website, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->websites[$websiteId] = $website;

        return new WebsiteResource($website);
    }

    /**
     * @return WebsiteResource[]
     */
    public function listWebsites(int $page = 1, int $limit = 100, ?WebsiteFilter $websiteFilter = null): array
    {
        $websites = array_values($this->websites);

        if ($websiteFilter !== null) {
            $websites = $this->applyWebsiteFilter($websites, $websiteFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $websites = array_slice($websites, $offset, $limit);

        return array_map(
            static fn (array $website): WebsiteResource => new WebsiteResource($website),
            $websites
        );
    }

    public function deleteWebsite(string $websiteId): bool
    {
        if (! isset($this->websites[$websiteId])) {
            return false;
        }

        unset($this->websites[$websiteId]);

        return true;
    }

    public function showMenu(string $menuId): ?MenuResource
    {
        $menu = $this->menus[$menuId] ?? null;

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

        $this->menus[$menuId] = $menu;

        return new MenuResource($menu);
    }

    public function updateMenu(string $menuId, UpdateMenuData $updateMenuData): MenuResource
    {
        $menu = $this->menus[$menuId] ?? null;

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

        $this->menus[$menuId] = $menu;

        return new MenuResource($menu);
    }

    /**
     * @return MenuResource[]
     */
    public function listMenus(string $websiteId): array
    {
        $menus = array_values(array_filter(
            $this->menus,
            static fn (array $menu): bool => ($menu['website_id'] ?? null) === $websiteId
        ));

        return array_map(
            static fn (array $menu): MenuResource => new MenuResource($menu),
            $menus
        );
    }

    public function deleteMenu(string $menuId): bool
    {
        if (! isset($this->menus[$menuId])) {
            return false;
        }

        unset($this->menus[$menuId]);

        return true;
    }

    protected function seedSampleWebsites(): void
    {
        $now = now()->toIso8601String();

        $this->websites = [
            '01J00000000000000000000001' => array_merge($this->defaultWebsiteAttributes(), [
                'id' => '01J00000000000000000000001',
                'status' => 'active',
                'name' => 'Sample Blog',
                'domains_count' => 2,
                'public_posts_count' => 42,
                'asset_route' => '/assets/{path}',
                'page_route' => '/page/{page}',
                'post_route' => '/post/{post}',
                'website_tag_route' => '/tag/{websiteTag}',
                'traffic_statistics' => [
                    'visits_7d' => 1280,
                    'pageviews_7d' => 3420,
                ],
                'created_at' => $now,
                'updated_at' => $now,
                'meta' => [
                    'site-name' => 'Sample Blog',
                    'home-seo-title' => 'Welcome to Sample Blog',
                    'home-seo-description' => 'A pseudo FlyCMS website for local development.',
                    'items-per-page' => '10',
                ],
            ]),
            '01J00000000000000000000002' => array_merge($this->defaultWebsiteAttributes(), [
                'id' => '01J00000000000000000000002',
                'status' => 'inactive',
                'name' => 'Demo Storefront',
                'domains_count' => 1,
                'public_posts_count' => 7,
                'asset_route' => '/static/{path}',
                'page_route' => '/pages/{page}',
                'post_route' => '/articles/{post}',
                'website_tag_route' => '/topics/{websiteTag}',
                'traffic_statistics' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'meta' => [
                    'site-name' => 'Demo Storefront',
                    'home-seo-title' => 'Demo Storefront',
                    'home-seo-description' => 'Inactive sample website.',
                    'items-per-page' => '20',
                ],
            ]),
        ];
    }

    protected function seedSampleMenus(): void
    {
        $now = now()->toIso8601String();

        $this->menus = [
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

    /**
     * @return array<string, mixed>
     */
    protected function defaultMenuAttributes(): array
    {
        return [
            'website_id' => null,
            'key' => 'main',
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultWebsiteAttributes(): array
    {
        return [
            'status' => 'active',
            'name' => 'Untitled Website',
            'domains_count' => 0,
            'public_posts_count' => 0,
            'asset_route' => null,
            'page_route' => null,
            'post_route' => null,
            'website_tag_route' => null,
            'traffic_statistics' => null,
            'meta' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $websites
     * @return array<int, array<string, mixed>>
     */
    protected function applyWebsiteFilter(array $websites, WebsiteFilter $websiteFilter): array
    {
        $filterData = $websiteFilter->getFilterData();

        if (isset($filterData['ids']) && is_string($filterData['ids']) && $filterData['ids'] !== '') {
            $ids = array_map('trim', explode(',', $filterData['ids']));
            $websites = array_values(array_filter(
                $websites,
                static fn (array $website): bool => in_array($website['id'] ?? null, $ids, true)
            ));
        }

        if (isset($filterData['search']) && is_string($filterData['search']) && $filterData['search'] !== '') {
            $search = strtolower($filterData['search']);
            $websites = array_values(array_filter(
                $websites,
                static function (array $website) use ($search): bool {
                    $name = strtolower((string) ($website['name'] ?? ''));

                    return str_contains($name, $search);
                }
            ));
        }

        return $websites;
    }
}
