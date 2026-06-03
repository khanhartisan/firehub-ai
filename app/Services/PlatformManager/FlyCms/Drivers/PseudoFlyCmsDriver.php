<?php

namespace App\Services\PlatformManager\FlyCms\Drivers;

use App\Contracts\PlatformManager\FlyCms\Filters\DomainFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\Resources\DomainResource;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;
use App\Services\PlatformManager\FlyCms\FlyCmsService;
use Illuminate\Support\Str;

class PseudoFlyCmsDriver extends FlyCmsService
{
    /** @var array<string, array<string, mixed>> */
    protected array $websites = [];

    /** @var array<string, array<string, mixed>> */
    protected array $menus = [];

    /** @var array<string, array<string, mixed>> */
    protected array $tags = [];

    /** @var array<string, array<string, mixed>> */
    protected array $domains = [];

    public function __construct()
    {
        $this->seedSampleWebsites();
        $this->seedSampleDomains();
        $this->seedSampleMenus();
        $this->seedSampleTags();
    }

    public function showDomain(string $domainId): ?DomainResource
    {
        $domain = $this->domains[$domainId] ?? null;

        if ($domain === null) {
            return null;
        }

        return new DomainResource($domain);
    }

    /**
     * @return DomainResource[]
     */
    public function listDomains(int $page = 1, int $limit = 100, ?DomainFilter $domainFilter = null): array
    {
        $domains = array_values($this->domains);

        if ($domainFilter !== null) {
            $domains = $this->applyDomainFilter($domains, $domainFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $domains = array_slice($domains, $offset, $limit);

        return array_map(
            static fn (array $domain): DomainResource => new DomainResource($domain),
            $domains
        );
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

    public function showTag(string $tagId): ?TagResource
    {
        $tag = $this->tags[$tagId] ?? null;

        if ($tag === null) {
            return null;
        }

        return new TagResource($tag);
    }

    public function createTag(CreateTagData $createTagData): TagResource
    {
        $tagId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createTagData->getData() ?? [];

        $tag = array_merge($this->defaultTagAttributes(), $data, [
            'id' => $tagId,
            'public_posts_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->tags[$tagId] = $tag;

        return new TagResource($tag);
    }

    public function updateTag(string $tagId, UpdateTagData $updateTagData): TagResource
    {
        $tag = $this->tags[$tagId] ?? null;

        if ($tag === null) {
            throw new \InvalidArgumentException("Tag [{$tagId}] not found.");
        }

        $data = array_filter(
            $updateTagData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        unset($data['website_id']);

        $tag = array_merge($tag, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->tags[$tagId] = $tag;

        return new TagResource($tag);
    }

    /**
     * @return TagResource[]
     */
    public function listTags(string $websiteId, int $page = 1, int $limit = 100, ?TagFilter $tagFilter = null): array
    {
        $tags = array_values(array_filter(
            $this->tags,
            static fn (array $tag): bool => ($tag['website_id'] ?? null) === $websiteId
        ));

        if ($tagFilter !== null) {
            $tags = $this->applyTagFilter($tags, $tagFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $tags = array_slice($tags, $offset, $limit);

        return array_map(
            static fn (array $tag): TagResource => new TagResource($tag),
            $tags
        );
    }

    public function deleteTag(string $tagId): bool
    {
        if (! isset($this->tags[$tagId])) {
            return false;
        }

        unset($this->tags[$tagId]);

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

    protected function seedSampleDomains(): void
    {
        $this->domains = [
            '01J00000000000000000000031' => array_merge($this->defaultDomainAttributes(), [
                'id' => '01J00000000000000000000031',
                'website_id' => '01J00000000000000000000001',
                'is_primary' => true,
                'is_alias' => false,
                'status' => 'active',
                'domain' => 'blog.example.com',
                'nameservers' => ['ns1.example.com', 'ns2.example.com'],
                'is_connected_to_server' => true,
            ]),
            '01J00000000000000000000032' => array_merge($this->defaultDomainAttributes(), [
                'id' => '01J00000000000000000000032',
                'website_id' => '01J00000000000000000000001',
                'is_primary' => false,
                'is_alias' => true,
                'status' => 'active',
                'domain' => 'www.blog.example.com',
                'nameservers' => ['ns1.example.com', 'ns2.example.com'],
                'is_connected_to_server' => true,
            ]),
            '01J00000000000000000000033' => array_merge($this->defaultDomainAttributes(), [
                'id' => '01J00000000000000000000033',
                'website_id' => '01J00000000000000000000002',
                'is_primary' => true,
                'is_alias' => false,
                'status' => 'inactive',
                'domain' => 'shop.demo.test',
                'nameservers' => ['ns1.demo.test'],
                'is_connected_to_server' => false,
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

    protected function seedSampleTags(): void
    {
        $now = now()->toIso8601String();

        $this->tags = [
            '01J00000000000000000000021' => array_merge($this->defaultTagAttributes(), [
                'id' => '01J00000000000000000000021',
                'website_id' => '01J00000000000000000000001',
                'is_featured' => true,
                'name' => 'Technology',
                'description' => 'Articles about technology and software.',
                'slug' => 'technology',
                'seo_title' => '{{ tag.name }} | Sample Blog',
                'seo_description' => 'Read the latest technology posts on Sample Blog.',
                'seo_h1' => '{{ tag.name }}',
                'content' => '<p>Technology tag landing page.</p>',
                'public_posts_count' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000022' => array_merge($this->defaultTagAttributes(), [
                'id' => '01J00000000000000000000022',
                'website_id' => '01J00000000000000000000001',
                'is_featured' => false,
                'name' => 'Lifestyle',
                'description' => null,
                'slug' => 'lifestyle',
                'seo_title' => null,
                'seo_description' => null,
                'seo_h1' => null,
                'content' => null,
                'public_posts_count' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000023' => array_merge($this->defaultTagAttributes(), [
                'id' => '01J00000000000000000000023',
                'website_id' => '01J00000000000000000000002',
                'is_featured' => false,
                'name' => 'Shop',
                'description' => 'Storefront product topics.',
                'slug' => 'shop',
                'seo_title' => 'Shop | Demo Storefront',
                'seo_description' => 'Browse products by topic.',
                'seo_h1' => 'Shop',
                'content' => null,
                'public_posts_count' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultDomainAttributes(): array
    {
        return [
            'website_id' => null,
            'is_primary' => false,
            'is_alias' => false,
            'status' => 'inactive',
            'domain' => 'example.com',
            'nameservers' => [],
            'is_connected_to_server' => false,
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
    protected function defaultTagAttributes(): array
    {
        return [
            'website_id' => null,
            'is_featured' => false,
            'name' => 'Untitled Tag',
            'description' => null,
            'slug' => 'untitled-tag',
            'seo_title' => null,
            'seo_description' => null,
            'seo_h1' => null,
            'content' => null,
            'public_posts_count' => 0,
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

    /**
     * @param  array<int, array<string, mixed>>  $tags
     * @return array<int, array<string, mixed>>
     */
    protected function applyTagFilter(array $tags, TagFilter $tagFilter): array
    {
        $filterData = $tagFilter->getFilterData();

        if (isset($filterData['name']) && is_string($filterData['name']) && $filterData['name'] !== '') {
            $search = strtolower($filterData['name']);
            $tags = array_values(array_filter(
                $tags,
                static function (array $tag) use ($search): bool {
                    $name = strtolower((string) ($tag['name'] ?? ''));

                    return str_contains($name, $search);
                }
            ));
        }

        return $tags;
    }

    /**
     * @param  array<int, array<string, mixed>>  $domains
     * @return array<int, array<string, mixed>>
     */
    protected function applyDomainFilter(array $domains, DomainFilter $domainFilter): array
    {
        $filterData = $domainFilter->getFilterData();

        if (isset($filterData['website_id']) && is_string($filterData['website_id']) && $filterData['website_id'] !== '') {
            $domains = array_values(array_filter(
                $domains,
                static fn (array $domain): bool => ($domain['website_id'] ?? null) === $filterData['website_id']
            ));
        }

        if (isset($filterData['domain']) && is_string($filterData['domain']) && $filterData['domain'] !== '') {
            $domains = array_values(array_filter(
                $domains,
                static fn (array $domain): bool => ($domain['domain'] ?? null) === $filterData['domain']
            ));
        }

        return $domains;
    }
}
