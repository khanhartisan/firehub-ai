<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns;

trait ManagesPseudoFlyCmsStore
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

    /** @var array<string, array<string, mixed>> */
    protected static array $meta = [];

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
        self::$meta = [];

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
        $instance->seedSampleMeta();
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
}
