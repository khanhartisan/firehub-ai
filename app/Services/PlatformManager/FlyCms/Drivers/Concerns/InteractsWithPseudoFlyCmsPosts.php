<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\Concerns;

use App\Contracts\PlatformManager\FlyCms\Filters\PostFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\CreatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\FlyCms\Resources\PostResource;
use Illuminate\Support\Str;

trait InteractsWithPseudoFlyCmsPosts
{
    public function showPost(string $postId): ?PostResource
    {
        $post = self::$posts[$postId] ?? null;

        if ($post === null) {
            return null;
        }

        return $this->toPostResource($post);
    }

    public function createPost(CreatePostData $createPostData): PostResource
    {
        $postId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createPostData->getData() ?? [];
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $post = array_merge($this->defaultPostAttributes(), $data, [
            'id' => $postId,
            'tag_ids' => is_array($tagIds) ? $tagIds : [],
            'restriction' => $data['restriction'] ?? 0,
            'lang' => $data['lang'] ?? 'default',
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => ($data['visibility'] ?? 'public') === 'public' ? $now : null,
        ]);

        self::$posts[$postId] = $post;

        return $this->toPostResource($post);
    }

    public function updatePost(UpdatePostData $updatePostData): PostResource
    {
        $data = $updatePostData->getData() ?? [];
        $postId = $data['id'] ?? null;

        if (! is_string($postId) || $postId === '') {
            throw new \InvalidArgumentException('Post id is required for update.');
        }

        $post = self::$posts[$postId] ?? null;

        if ($post === null) {
            throw new \InvalidArgumentException("Post [{$postId}] not found.");
        }

        unset($data['id'], $data['website_id']);

        if (isset($data['tag_ids'])) {
            $data['tag_ids'] = is_array($data['tag_ids']) ? $data['tag_ids'] : [];
        }

        $data = array_filter(
            $data,
            static fn (mixed $value): bool => $value !== null
        );

        $post = array_merge($post, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        if (array_key_exists('visibility', $data)) {
            $post['published_at'] = $data['visibility'] === 'public'
                ? ($post['published_at'] ?? now()->toIso8601String())
                : null;
        }

        self::$posts[$postId] = $post;

        return $this->toPostResource($post);
    }

    /**
     * @return PostResource[]
     */
    public function listPosts(string $websiteId,
                              int $page = 1,
                              int $limit = 100,
                              ?int $orderDirection = null,
                              ?PostFilter $postFilter = null): array
    {
        $posts = array_values(array_filter(
            self::$posts,
            static fn (array $post): bool => ($post['website_id'] ?? null) === $websiteId
        ));

        if ($postFilter !== null) {
            $posts = $this->applyPostFilter($posts, $postFilter);
        }

        if ($orderDirection !== null) {
            usort($posts, function (array $left, array $right) use ($orderDirection): int {
                $leftTime = strtotime((string) ($left['created_at'] ?? ''));
                $rightTime = strtotime((string) ($right['created_at'] ?? ''));

                return $orderDirection === -1
                    ? $rightTime <=> $leftTime
                    : $leftTime <=> $rightTime;
            });
        }

        $offset = max(0, ($page - 1) * $limit);
        $posts = array_slice($posts, $offset, $limit);

        return array_map(
            fn (array $post): PostResource => $this->toPostResource($post),
            $posts
        );
    }

    public function deletePost(string $postId): bool
    {
        if (! isset(self::$posts[$postId])) {
            return false;
        }

        unset(self::$posts[$postId]);

        return true;
    }
    protected function seedSamplePosts(): void
    {
        $older = now()->subDays(2)->toIso8601String();
        $newer = now()->subDay()->toIso8601String();
        $now = now()->toIso8601String();

        self::$posts = [
            '01J00000000000000000000051' => array_merge($this->defaultPostAttributes(), [
                'id' => '01J00000000000000000000051',
                'website_id' => '01J00000000000000000000001',
                'slug' => 'hello-world',
                'title' => 'Hello World',
                'description' => 'Our first blog post.',
                'content' => '<p>Welcome to Sample Blog.</p>',
                'seo_title' => 'Hello World | Sample Blog',
                'seo_description' => 'Read our first post on Sample Blog.',
                'visibility' => 'public',
                'restriction' => 0,
                'lang' => 'default',
                'tag_ids' => ['01J00000000000000000000021'],
                'thumbnail_file_id' => '01J00000000000000000000071',
                'created_at' => $older,
                'updated_at' => $older,
                'published_at' => $older,
            ]),
            '01J00000000000000000000052' => array_merge($this->defaultPostAttributes(), [
                'id' => '01J00000000000000000000052',
                'website_id' => '01J00000000000000000000001',
                'slug' => 'weekend-ideas',
                'title' => 'Weekend Ideas',
                'description' => 'Things to do this weekend.',
                'content' => '<p>Relax and recharge.</p>',
                'seo_title' => null,
                'seo_description' => null,
                'visibility' => 'public',
                'restriction' => 1,
                'lang' => 'default',
                'tag_ids' => ['01J00000000000000000000022'],
                'created_at' => $newer,
                'updated_at' => $newer,
                'published_at' => $newer,
            ]),
            '01J00000000000000000000053' => array_merge($this->defaultPostAttributes(), [
                'id' => '01J00000000000000000000053',
                'website_id' => '01J00000000000000000000002',
                'slug' => 'new-arrivals',
                'title' => 'New Arrivals',
                'description' => 'Latest products in the shop.',
                'content' => '<p>Check out what is new.</p>',
                'seo_title' => 'New Arrivals | Demo Storefront',
                'seo_description' => 'Browse the latest products.',
                'visibility' => 'private',
                'restriction' => 0,
                'lang' => 'default',
                'tag_ids' => ['01J00000000000000000000023'],
                'created_at' => $now,
                'updated_at' => $now,
                'published_at' => null,
            ]),
        ];
    }
    protected function defaultPostAttributes(): array
    {
        return [
            'website_id' => null,
            'slug' => 'untitled-post',
            'title' => 'Untitled Post',
            'description' => null,
            'content' => null,
            'seo_title' => null,
            'seo_description' => null,
            'visibility' => 'public',
            'restriction' => 0,
            'lang' => 'default',
            'tag_ids' => [],
            'thumbnail_file_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $post
     */
    protected function toPostResource(array $post): PostResource
    {
        $resourceData = $post;
        $resourceData['tags'] = $this->resolvePostTags($post['tag_ids'] ?? []);
        unset($resourceData['tag_ids']);

        $thumbnailFileId = $post['thumbnail_file_id'] ?? null;

        if (is_string($thumbnailFileId) && $thumbnailFileId !== '') {
            $file = self::$files[$thumbnailFileId] ?? null;
            $resourceData['thumbnailFile'] = $file !== null
                ? $this->fileRecordForOutput($file)
                : null;
        } else {
            $resourceData['thumbnailFile'] = null;
        }

        return new PostResource($resourceData);
    }

    /**
     * @param  list<string>  $tagIds
     * @return list<array<string, mixed>>
     */
    protected function resolvePostTags(array $tagIds): array
    {
        $tags = [];

        foreach ($tagIds as $tagId) {
            $tag = self::$tags[$tagId] ?? null;

            if ($tag === null) {
                continue;
            }

            $tags[] = $tag;
        }

        return $tags;
    }
    protected function applyPostFilter(array $posts, PostFilter $postFilter): array
    {
        $filterData = $postFilter->getFilterData();

        if (isset($filterData['ids']) && is_string($filterData['ids']) && $filterData['ids'] !== '') {
            $ids = array_map('trim', explode(',', $filterData['ids']));
            $posts = array_values(array_filter(
                $posts,
                static fn (array $post): bool => in_array($post['id'] ?? null, $ids, true)
            ));
        }

        if (isset($filterData['restriction']) && is_int($filterData['restriction'])) {
            $posts = array_values(array_filter(
                $posts,
                static fn (array $post): bool => ($post['restriction'] ?? null) === $filterData['restriction']
            ));
        }

        if (isset($filterData['search']) && is_string($filterData['search']) && $filterData['search'] !== '') {
            $search = strtolower($filterData['search']);
            $posts = array_values(array_filter(
                $posts,
                static function (array $post) use ($search): bool {
                    $fields = [
                        (string) ($post['title'] ?? ''),
                        (string) ($post['description'] ?? ''),
                        (string) ($post['content'] ?? ''),
                        (string) ($post['slug'] ?? ''),
                    ];

                    foreach ($fields as $field) {
                        if (str_contains(strtolower($field), $search)) {
                            return true;
                        }
                    }

                    return false;
                }
            ));
        }

        if (isset($filterData['slug']) && is_string($filterData['slug']) && $filterData['slug'] !== '') {
            $posts = array_values(array_filter(
                $posts,
                static fn (array $post): bool => ($post['slug'] ?? null) === $filterData['slug']
            ));
        }

        if (isset($filterData['visibility']) && is_string($filterData['visibility']) && $filterData['visibility'] !== '') {
            $posts = array_values(array_filter(
                $posts,
                static fn (array $post): bool => ($post['visibility'] ?? null) === $filterData['visibility']
            ));
        }

        if (isset($filterData['tag_id']) && is_string($filterData['tag_id']) && $filterData['tag_id'] !== '') {
            $posts = array_values(array_filter(
                $posts,
                static function (array $post) use ($filterData): bool {
                    $tagIds = $post['tag_ids'] ?? [];

                    return is_array($tagIds) && in_array($filterData['tag_id'], $tagIds, true);
                }
            ));
        }

        return $posts;
    }
}
