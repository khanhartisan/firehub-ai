<?php

namespace App\Contracts\PlatformManager\FlyCms;

use App\Contracts\PlatformManager\ArticlePlatformManager;
use App\Contracts\PlatformManager\FlyCms\Managers\AuthorManager;
use App\Contracts\PlatformManager\FlyCms\Managers\DomainManager;
use App\Contracts\PlatformManager\FlyCms\Managers\FileManager;
use App\Contracts\PlatformManager\FlyCms\Managers\MenuManager;
use App\Contracts\PlatformManager\FlyCms\Managers\MetaManager;
use App\Contracts\PlatformManager\FlyCms\Managers\PageManager;
use App\Contracts\PlatformManager\FlyCms\Managers\PostManager;
use App\Contracts\PlatformManager\FlyCms\Managers\RoleManager;
use App\Contracts\PlatformManager\FlyCms\Managers\TagManager;
use App\Contracts\PlatformManager\FlyCms\Managers\ThemeManager;
use App\Contracts\PlatformManager\FlyCms\Managers\UserManager;
use App\Contracts\PlatformManager\FlyCms\Managers\WebsiteManager;
use App\Contracts\PlatformManager\PlatformManager;

interface FlyCms
    extends
    ArticlePlatformManager,
    AuthorManager,
    PlatformManager,
    DomainManager,
    FileManager,
    MenuManager,
    MetaManager,
    PageManager,
    PostManager,
    RoleManager,
    TagManager,
    ThemeManager,
    UserManager,
    WebsiteManager
{}
