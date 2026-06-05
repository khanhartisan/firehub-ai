<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\Concerns;

use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\CreatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\UpdatePageData;
use App\Contracts\PlatformManager\FlyCms\Resources\PageResource;
use Illuminate\Support\Str;

trait InteractsWithPseudoFlyCmsPages
{
    public function showPage(string $pageId): ?PageResource
    {
        $page = self::$pages[$pageId] ?? null;

        if ($page === null) {
            return null;
        }

        return new PageResource($page);
    }

    public function createPage(CreatePageData $createPageData): PageResource
    {
        $pageId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createPageData->getData() ?? [];

        $page = array_merge($this->defaultPageAttributes(), $data, [
            'id' => $pageId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::$pages[$pageId] = $page;

        return new PageResource($page);
    }

    public function updatePage(string $pageId, UpdatePageData $updatePageData): PageResource
    {
        $page = self::$pages[$pageId] ?? null;

        if ($page === null) {
            throw new \InvalidArgumentException("Page [{$pageId}] not found.");
        }

        $data = array_filter(
            $updatePageData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        $page = array_merge($page, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        self::$pages[$pageId] = $page;

        return new PageResource($page);
    }

    /**
     * @return PageResource[]
     */
    public function listPages(string $websiteId, int $page = 1, int $limit = 100): array
    {
        $pages = array_values(array_filter(
            self::$pages,
            static fn (array $page): bool => ($page['website_id'] ?? null) === $websiteId
        ));

        $offset = max(0, ($page - 1) * $limit);
        $pages = array_slice($pages, $offset, $limit);

        return array_map(
            static fn (array $page): PageResource => new PageResource($page),
            $pages
        );
    }

    public function deletePage(string $pageId): void
    {
        if (! isset(self::$pages[$pageId])) {
            throw new \InvalidArgumentException("Page [{$pageId}] not found.");
        }

        unset(self::$pages[$pageId]);
    }
    protected function seedSamplePages(): void
    {
        $now = now()->toIso8601String();

        self::$pages = [
            '01J00000000000000000000041' => array_merge($this->defaultPageAttributes(), [
                'id' => '01J00000000000000000000041',
                'website_id' => '01J00000000000000000000001',
                'slug' => 'about',
                'title' => 'About Us',
                'seo_title' => 'About Us | Sample Blog',
                'seo_description' => 'Learn more about Sample Blog.',
                'content' => '<p>Welcome to our about page.</p>',
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000042' => array_merge($this->defaultPageAttributes(), [
                'id' => '01J00000000000000000000042',
                'website_id' => '01J00000000000000000000001',
                'slug' => 'contact',
                'title' => 'Contact',
                'seo_title' => null,
                'seo_description' => null,
                'content' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000043' => array_merge($this->defaultPageAttributes(), [
                'id' => '01J00000000000000000000043',
                'website_id' => '01J00000000000000000000002',
                'slug' => 'shipping',
                'title' => 'Shipping Policy',
                'seo_title' => 'Shipping | Demo Storefront',
                'seo_description' => 'Shipping information for Demo Storefront.',
                'content' => '<p>Shipping details.</p>',
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }
    protected function defaultPageAttributes(): array
    {
        return [
            'website_id' => null,
            'slug' => 'untitled-page',
            'title' => 'Untitled Page',
            'seo_title' => null,
            'seo_description' => null,
            'content' => null,
        ];
    }
}
