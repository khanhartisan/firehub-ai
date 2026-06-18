<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns;

use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use Illuminate\Support\Str;

trait InteractsWithPseudoFlyCmsTags
{
    public function showTag(string $tagId): ?TagResource
    {
        $tag = self::$tags[$tagId] ?? null;

        if ($tag === null) {
            return null;
        }

        return $this->toTagResource($tag);
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

        self::$tags[$tagId] = $tag;

        return $this->toTagResource($tag);
    }

    public function updateTag(string $tagId, UpdateTagData $updateTagData): TagResource
    {
        $tag = self::$tags[$tagId] ?? null;

        if ($tag === null) {
            throw new \InvalidArgumentException("Tag [{$tagId}] not found.");
        }

        $data = array_filter(
            $updateTagData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        unset($data['website_id'], $data['name']);

        $tag = array_merge($tag, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        self::$tags[$tagId] = $tag;

        return $this->toTagResource($tag);
    }

    /**
     * @return TagResource[]
     */
    public function listTags(string $websiteId, int $page = 1, int $limit = 100, ?TagFilter $tagFilter = null): array
    {
        $tags = array_values(array_filter(
            self::$tags,
            static fn (array $tag): bool => ($tag['website_id'] ?? null) === $websiteId
        ));

        if ($tagFilter !== null) {
            $tags = $this->applyTagFilter($tags, $tagFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $tags = array_slice($tags, $offset, $limit);

        return array_map(
            fn (array $tag): TagResource => $this->toTagResource($tag),
            $tags
        );
    }

    public function deleteTag(string $tagId): bool
    {
        if (! isset(self::$tags[$tagId])) {
            return false;
        }

        unset(self::$tags[$tagId]);

        return true;
    }
    protected function seedSampleTags(): void
    {
        $now = now()->toIso8601String();

        self::$tags = [
            '01J00000000000000000000021' => array_merge($this->defaultTagAttributes(), [
                'id' => '01J00000000000000000000021',
                'website_id' => '01J00000000000000000000001',
                'is_featured' => true,
                'name' => 'Technology',
                'display_name' => 'Technology',
                'description' => 'Articles about technology and software.',
                'slug' => 'technology',
                'seo_title' => '{{ tag.name }} | Sample Blog',
                'seo_description' => 'Read the latest technology posts on Sample Blog.',
                'seo_h1' => '{{ tag.name }}',
                'content' => '<p>Technology tag landing page.</p>',
                'public_posts_count' => 12,
                'thumbnail_file_id' => '01J00000000000000000000071',
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000022' => array_merge($this->defaultTagAttributes(), [
                'id' => '01J00000000000000000000022',
                'website_id' => '01J00000000000000000000001',
                'is_featured' => false,
                'name' => 'Lifestyle',
                'display_name' => 'Lifestyle',
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
                'display_name' => 'Shop',
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
    protected function defaultTagAttributes(): array
    {
        return [
            'website_id' => null,
            'is_featured' => false,
            'name' => 'Untitled Tag',
            'display_name' => 'Untitled Tag',
            'description' => null,
            'slug' => 'untitled-tag',
            'seo_title' => null,
            'seo_description' => null,
            'seo_h1' => null,
            'content' => null,
            'public_posts_count' => 0,
            'thumbnail_file_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $tag
     */
    protected function toTagResource(array $tag): TagResource
    {
        $resourceData = $tag;
        $thumbnailFileId = $tag['thumbnail_file_id'] ?? null;

        if (is_string($thumbnailFileId) && $thumbnailFileId !== '') {
            $file = self::$files[$thumbnailFileId] ?? null;
            $resourceData['thumbnailFile'] = $file !== null
                ? $this->fileRecordForOutput($file)
                : null;
        } else {
            $resourceData['thumbnailFile'] = null;
        }

        return new TagResource($resourceData);
    }
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
}
