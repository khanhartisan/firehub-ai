<?php

namespace App\Services\PlatformManager\FlyCms\Drivers;

use App\Contracts\PlatformManager\FlyCms\Filters\DomainFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\PostFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\RoleFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\ThemeFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\UserFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\UpdateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\CreatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\CreateRoleData;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\UpdateRoleData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\CreatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\UpdatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\CreateUserData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\UpdateUserData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\Resources\DomainResource;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use App\Contracts\PlatformManager\FlyCms\Resources\PageResource;
use App\Contracts\PlatformManager\FlyCms\Resources\PostResource;
use App\Contracts\PlatformManager\FlyCms\Resources\RoleResource;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use App\Contracts\PlatformManager\FlyCms\Resources\ThemeResource;
use App\Contracts\PlatformManager\FlyCms\Resources\UserResource;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;
use App\Services\PlatformManager\FlyCms\FlyCmsService;
use Illuminate\Support\Str;

class PseudoFlyCmsDriver extends FlyCmsService
{
    /** @var array<string, array<string, mixed>> */
    protected static array $websites = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $menus = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $tags = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $domains = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $pages = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $posts = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $users = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $roles = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $files = [];

    /** @var array<string, array<string, mixed>> */
    protected static array $themes = [];

    public function __construct()
    {
        self::ensureSeeded();
    }

    public static function reset(): void
    {
        self::$websites = [];
        self::$menus = [];
        self::$tags = [];
        self::$domains = [];
        self::$pages = [];
        self::$posts = [];
        self::$users = [];
        self::$roles = [];
        self::$files = [];
        self::$themes = [];

        self::ensureSeeded();
    }

    protected static function ensureSeeded(): void
    {
        if (self::$websites !== []) {
            return;
        }

        /** @var self $instance */
        $instance = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->seedSampleWebsites();
        $instance->seedSampleDomains();
        $instance->seedSampleThemes();
        $instance->seedSampleMenus();
        $instance->seedSampleTags();
        $instance->seedSamplePages();
        $instance->seedSamplePosts();
        $instance->seedSampleRoles();
        $instance->seedSampleUsers();
        $instance->seedSampleFiles();
    }

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

    public function showFile(string $fileId): ?FileResource
    {
        $file = self::$files[$fileId] ?? null;

        if ($file === null) {
            return null;
        }

        return $this->toFileResource($file);
    }

    public function createFile(mixed $data, CreateFileData $createFileData): FileResource
    {
        $content = $this->readFileData($data);
        $mutationData = $createFileData->getData() ?? [];
        $ext = (string) ($mutationData['ext'] ?? 'jpg');
        $fileId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $key = 'uploads/'.($mutationData['filename'] ?? $fileId).'.'.$ext;

        $file = array_merge($this->defaultFileAttributes(), [
            'id' => $fileId,
            'code' => $mutationData['code'] ?? null,
            'key' => $key,
            'type' => $this->resolveFileTypeFromExt($ext),
            'mime' => $this->resolveMimeFromExt($ext),
            'size' => strlen($content),
            'information' => $mutationData['information'] ?? null,
            'is_uploaded' => true,
            'url' => $this->pseudoFileUrl($key),
            'created_at' => $now,
        ]);

        self::$files[$fileId] = $file;

        return $this->toFileResource($file);
    }

    public function updateFile(string $fileId, UpdateFileData $updateFileData): FileResource
    {
        $file = self::$files[$fileId] ?? null;

        if ($file === null) {
            throw new \InvalidArgumentException("File [{$fileId}] not found.");
        }

        $data = array_filter(
            $updateFileData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        $file = array_merge($file, $data);

        self::$files[$fileId] = $file;

        return $this->toFileResource($file);
    }

    /**
     * @return FileResource[]
     */
    public function listFiles(int $page = 1,
                              int $limit = 100,
                              ?int $orderDirection = null,
                              ?FileFilter $fileFilter = null): array
    {
        $files = array_values(self::$files);

        if ($fileFilter !== null) {
            $files = $this->applyFileFilter($files, $fileFilter);
        }

        if ($orderDirection !== null) {
            usort($files, function (array $left, array $right) use ($orderDirection): int {
                $leftTime = strtotime((string) ($left['created_at'] ?? ''));
                $rightTime = strtotime((string) ($right['created_at'] ?? ''));

                return $orderDirection === -1
                    ? $rightTime <=> $leftTime
                    : $leftTime <=> $rightTime;
            });
        }

        $offset = max(0, ($page - 1) * $limit);
        $files = array_slice($files, $offset, $limit);

        return array_map(
            fn (array $file): FileResource => $this->toFileResource($file),
            $files
        );
    }

    public function deleteFile(string $fileId): FileResource
    {
        $file = self::$files[$fileId] ?? null;

        if ($file === null) {
            throw new \InvalidArgumentException("File [{$fileId}] not found.");
        }

        $resource = $this->toFileResource($file);

        unset(self::$files[$fileId]);

        return $resource;
    }

    public function showDomain(string $domainId): ?DomainResource
    {
        $domain = self::$domains[$domainId] ?? null;

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
        $domains = array_values(self::$domains);

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

    public function showMenu(string $menuId): ?MenuResource
    {
        $menu = self::$menus[$menuId] ?? null;

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

        self::$menus[$menuId] = $menu;

        return new MenuResource($menu);
    }

    public function updateMenu(string $menuId, UpdateMenuData $updateMenuData): MenuResource
    {
        $menu = self::$menus[$menuId] ?? null;

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

        self::$menus[$menuId] = $menu;

        return new MenuResource($menu);
    }

    /**
     * @return MenuResource[]
     */
    public function listMenus(string $websiteId): array
    {
        $menus = array_values(array_filter(
            self::$menus,
            static fn (array $menu): bool => ($menu['website_id'] ?? null) === $websiteId
        ));

        return array_map(
            static fn (array $menu): MenuResource => new MenuResource($menu),
            $menus
        );
    }

    public function deleteMenu(string $menuId): bool
    {
        if (! isset(self::$menus[$menuId])) {
            return false;
        }

        unset(self::$menus[$menuId]);

        return true;
    }

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

        unset($data['website_id']);

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

    public function showUser(string $userId): ?UserResource
    {
        $user = self::$users[$userId] ?? null;

        if ($user === null) {
            return null;
        }

        return $this->toUserResource($user);
    }

    public function createUser(CreateUserData $createUserData): UserResource
    {
        $userId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createUserData->getData() ?? [];
        unset($data['password']);

        $user = array_merge($this->defaultUserAttributes(), $data, [
            'id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::$users[$userId] = $user;

        return $this->toUserResource($user);
    }

    public function updateUser(string $userId, UpdateUserData $updateUserData): UserResource
    {
        $user = self::$users[$userId] ?? null;

        if ($user === null) {
            throw new \InvalidArgumentException("User [{$userId}] not found.");
        }

        $data = array_filter(
            $updateUserData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );
        unset($data['password']);

        $user = array_merge($user, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        self::$users[$userId] = $user;

        return $this->toUserResource($user);
    }

    /**
     * @return UserResource[]
     */
    public function listUsers(int $page = 1, int $limit = 100, ?UserFilter $userFilter = null): array
    {
        $users = array_values(self::$users);

        if ($userFilter !== null) {
            $users = $this->applyUserFilter($users, $userFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $users = array_slice($users, $offset, $limit);

        return array_map(
            fn (array $user): UserResource => $this->toUserResource($user),
            $users
        );
    }

    public function deleteUser(string $userId): bool
    {
        if (! isset(self::$users[$userId])) {
            return false;
        }

        unset(self::$users[$userId]);

        return true;
    }

    public function showRole(string $roleId): ?RoleResource
    {
        $role = self::$roles[$roleId] ?? null;

        if ($role === null) {
            return null;
        }

        return new RoleResource($role);
    }

    public function createRole(CreateRoleData $createRoleData): RoleResource
    {
        $roleId = (string) Str::ulid();
        $now = now()->toIso8601String();
        $data = $createRoleData->getData() ?? [];

        $role = array_merge($this->defaultRoleAttributes(), $data, [
            'id' => $roleId,
            'abilities' => is_array($data['abilities'] ?? null) ? $data['abilities'] : [],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::$roles[$roleId] = $role;

        return new RoleResource($role);
    }

    public function updateRole(string $roleId, UpdateRoleData $updateRoleData): RoleResource
    {
        $role = self::$roles[$roleId] ?? null;

        if ($role === null) {
            throw new \InvalidArgumentException("Role [{$roleId}] not found.");
        }

        $data = array_filter(
            $updateRoleData->getData() ?? [],
            static fn (mixed $value): bool => $value !== null
        );

        $role = array_merge($role, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        self::$roles[$roleId] = $role;

        return new RoleResource($role);
    }

    /**
     * @return RoleResource[]
     */
    public function listRoles(int $page = 1, int $perPage = 100, ?RoleFilter $roleFilter = null): array
    {
        $roles = array_values(self::$roles);

        if ($roleFilter !== null) {
            $roles = $this->applyRoleFilter($roles, $roleFilter);
        }

        $offset = max(0, ($page - 1) * $perPage);
        $roles = array_slice($roles, $offset, $perPage);

        return array_map(
            static fn (array $role): RoleResource => new RoleResource($role),
            $roles
        );
    }

    public function deleteRole(string $roleId): RoleResource
    {
        $role = self::$roles[$roleId] ?? null;

        if ($role === null) {
            throw new \InvalidArgumentException("Role [{$roleId}] not found.");
        }

        $resource = new RoleResource($role);

        unset(self::$roles[$roleId]);

        return $resource;
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

    protected function seedSampleDomains(): void
    {
        self::$domains = [
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

        self::$menus = [
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

        self::$tags = [
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
                'thumbnail_file_id' => '01J00000000000000000000071',
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

    /**
     * @return array<string, mixed>
     */
    protected function defaultFileAttributes(): array
    {
        return [
            'code' => null,
            'user_id' => null,
            'key' => '',
            'type' => 'unknown',
            'mime' => 'application/octet-stream',
            'size' => 0,
            'information' => null,
            'is_uploaded' => false,
            'url' => null,
            'post_id' => null,
            'created_at' => null,
        ];
    }

    protected function toFileResource(array $file): FileResource
    {
        return new FileResource($this->fileRecordForOutput($file));
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    protected function fileRecordForOutput(array $file): array
    {
        unset($file['post_id'], $file['created_at']);

        return $file;
    }

    protected function pseudoFileUrl(string $key): string
    {
        return 'https://cdn.pseudo.flycms.test/'.$key;
    }

    protected function resolveMimeFromExt(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }

    protected function resolveFileTypeFromExt(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
            'mp4', 'webm' => 'video',
            default => 'unknown',
        };
    }

    protected function readFileData(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_resource($data)) {
            $content = stream_get_contents($data);

            return is_string($content) ? $content : '';
        }

        throw new \InvalidArgumentException('File data must be a string or stream resource.');
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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

    protected function seedSampleFiles(): void
    {
        $older = now()->subDays(2)->toIso8601String();
        $newer = now()->subDay()->toIso8601String();

        self::$files = [
            '01J00000000000000000000071' => array_merge($this->defaultFileAttributes(), [
                'id' => '01J00000000000000000000071',
                'code' => 'hero-banner',
                'user_id' => '01J00000000000000000000061',
                'key' => 'uploads/hero-banner.jpg',
                'type' => 'image',
                'mime' => 'image/jpeg',
                'size' => 2048,
                'information' => [
                    'alt' => 'Sample blog hero image',
                ],
                'is_uploaded' => true,
                'url' => $this->pseudoFileUrl('uploads/hero-banner.jpg'),
                'post_id' => '01J00000000000000000000051',
                'created_at' => $older,
            ]),
            '01J00000000000000000000072' => array_merge($this->defaultFileAttributes(), [
                'id' => '01J00000000000000000000072',
                'code' => null,
                'user_id' => '01J00000000000000000000062',
                'key' => 'uploads/weekend-ideas.webp',
                'type' => 'image',
                'mime' => 'image/webp',
                'size' => 4096,
                'information' => null,
                'is_uploaded' => true,
                'url' => $this->pseudoFileUrl('uploads/weekend-ideas.webp'),
                'post_id' => '01J00000000000000000000052',
                'created_at' => $newer,
            ]),
            '01J00000000000000000000073' => array_merge($this->defaultFileAttributes(), [
                'id' => '01J00000000000000000000073',
                'code' => 'storefront-intro',
                'user_id' => '01J00000000000000000000062',
                'key' => 'uploads/storefront-intro.mp4',
                'type' => 'video',
                'mime' => 'video/mp4',
                'size' => 8192,
                'information' => [
                    'duration' => 12,
                ],
                'is_uploaded' => false,
                'url' => null,
                'post_id' => null,
                'created_at' => now()->toIso8601String(),
            ]),
        ];
    }

    protected function seedSampleRoles(): void
    {
        $now = now()->toIso8601String();

        self::$roles = [
            '01J00000000000000000000091' => array_merge($this->defaultRoleAttributes(), [
                'id' => '01J00000000000000000000091',
                'name' => 'Editor',
                'abilities' => ['posts.create', 'posts.update', 'posts.delete'],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000092' => array_merge($this->defaultRoleAttributes(), [
                'id' => '01J00000000000000000000092',
                'name' => 'Manager',
                'abilities' => ['posts.create', 'posts.update', 'posts.delete', 'users.manage'],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }

    protected function seedSampleUsers(): void
    {
        $now = now()->toIso8601String();

        self::$users = [
            '01J00000000000000000000061' => array_merge($this->defaultUserAttributes(), [
                'id' => '01J00000000000000000000061',
                'role_id' => '01J00000000000000000000091',
                'name' => 'Alex Editor',
                'email' => 'alex@example.com',
                'website_ids' => ['01J00000000000000000000001'],
                'branch_ids' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            '01J00000000000000000000062' => array_merge($this->defaultUserAttributes(), [
                'id' => '01J00000000000000000000062',
                'role_id' => '01J00000000000000000000092',
                'name' => 'Sam Manager',
                'email' => 'sam@example.com',
                'level' => 5,
                'website_ids' => ['01J00000000000000000000001', '01J00000000000000000000002'],
                'branch_ids' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultRoleAttributes(): array
    {
        return [
            'name' => 'Untitled Role',
            'abilities' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultUserAttributes(): array
    {
        return [
            'role_id' => null,
            'is_super' => false,
            'name' => 'Untitled User',
            'email' => 'user@example.com',
            'email_verified_at' => null,
            'level' => 0,
            'balance' => 0.0,
            'api_key' => null,
            'log_api_methods' => null,
            'api_logs_limit' => 10000,
            'website_ids' => [],
            'branch_ids' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function userRecordForOutput(array $user): array
    {
        unset($user['website_ids'], $user['branch_ids'], $user['password']);

        return $user;
    }

    protected function toUserResource(array $user): UserResource
    {
        return new UserResource($this->userRecordForOutput($user));
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
     * @param  array<int, array<string, mixed>>  $themes
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @param  array<int, array<string, mixed>>  $roles
     * @return array<int, array<string, mixed>>
     */
    protected function applyRoleFilter(array $roles, RoleFilter $roleFilter): array
    {
        $filterData = $roleFilter->getFilterData();

        if (isset($filterData['search']) && is_string($filterData['search']) && $filterData['search'] !== '') {
            $search = strtolower($filterData['search']);
            $roles = array_values(array_filter(
                $roles,
                static function (array $role) use ($search): bool {
                    $name = strtolower((string) ($role['name'] ?? ''));

                    return str_contains($name, $search);
                }
            ));
        }

        return $roles;
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<int, array<string, mixed>>
     */
    protected function applyUserFilter(array $users, UserFilter $userFilter): array
    {
        $filterData = $userFilter->getFilterData();

        if (isset($filterData['ids']) && is_string($filterData['ids']) && $filterData['ids'] !== '') {
            $ids = array_map('trim', explode(',', $filterData['ids']));
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool => in_array($user['id'] ?? null, $ids, true)
            ));
        }

        if (isset($filterData['search']) && is_string($filterData['search']) && $filterData['search'] !== '') {
            $search = strtolower($filterData['search']);
            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($search): bool {
                    $name = strtolower((string) ($user['name'] ?? ''));

                    return str_contains($name, $search);
                }
            ));
        }

        if (isset($filterData['website_id']) && is_string($filterData['website_id']) && $filterData['website_id'] !== '') {
            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($filterData): bool {
                    $websiteIds = $user['website_ids'] ?? [];

                    return is_array($websiteIds) && in_array($filterData['website_id'], $websiteIds, true);
                }
            ));
        }

        if (isset($filterData['branch_id']) && is_string($filterData['branch_id']) && $filterData['branch_id'] !== '') {
            $users = array_values(array_filter(
                $users,
                static function (array $user) use ($filterData): bool {
                    $branchIds = $user['branch_ids'] ?? [];

                    return is_array($branchIds) && in_array($filterData['branch_id'], $branchIds, true);
                }
            ));
        }

        if (isset($filterData['role_id']) && is_string($filterData['role_id']) && $filterData['role_id'] !== '') {
            $users = array_values(array_filter(
                $users,
                static fn (array $user): bool => ($user['role_id'] ?? null) === $filterData['role_id']
            ));
        }

        return $users;
    }

    /**
     * @param  array<int, array<string, mixed>>  $posts
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array<string, mixed>>
     */
    protected function applyFileFilter(array $files, FileFilter $fileFilter): array
    {
        $filterData = $fileFilter->getFilterData();

        if (isset($filterData['ids']) && is_string($filterData['ids']) && $filterData['ids'] !== '') {
            $ids = array_map('trim', explode(',', $filterData['ids']));
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => in_array($file['id'] ?? null, $ids, true)
            ));
        }

        if (isset($filterData['post_id']) && is_string($filterData['post_id']) && $filterData['post_id'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['post_id'] ?? null) === $filterData['post_id']
            ));
        }

        if (isset($filterData['code']) && is_string($filterData['code']) && $filterData['code'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['code'] ?? null) === $filterData['code']
            ));
        }

        if (isset($filterData['key']) && is_string($filterData['key']) && $filterData['key'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['key'] ?? null) === $filterData['key']
            ));
        }

        if (isset($filterData['type']) && is_string($filterData['type']) && $filterData['type'] !== '') {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['type'] ?? null) === $filterData['type']
            ));
        }

        return $files;
    }
}
