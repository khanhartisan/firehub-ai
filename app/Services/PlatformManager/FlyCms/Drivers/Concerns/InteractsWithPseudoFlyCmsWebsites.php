<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\Concerns;

use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;
use Illuminate\Support\Str;

trait InteractsWithPseudoFlyCmsWebsites
{
    public function showWebsite(string $websiteId): ?WebsiteResource
    {
        $website = self::$websites[$websiteId] ?? null;

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

        self::$websites[$websiteId] = $website;

        return new WebsiteResource($website);
    }

    public function updateWebsite(string $websiteId, UpdateWebsiteData $updateWebsiteData): WebsiteResource
    {
        $website = self::$websites[$websiteId] ?? null;

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

        self::$websites[$websiteId] = $website;

        return new WebsiteResource($website);
    }

    /**
     * @return WebsiteResource[]
     */
    public function listWebsites(int $page = 1, int $limit = 100, ?WebsiteFilter $websiteFilter = null): array
    {
        $websites = array_values(self::$websites);

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
        if (! isset(self::$websites[$websiteId])) {
            return false;
        }

        unset(self::$websites[$websiteId]);

        return true;
    }
    protected function seedSampleWebsites(): void
    {
        $now = now()->toIso8601String();

        self::$websites = [
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
                'theme_id' => '01J00000000000000000000081',
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
                'theme_id' => '01J00000000000000000000082',
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
            'theme_id' => null,
            'traffic_statistics' => null,
            'meta' => [],
        ];
    }
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
