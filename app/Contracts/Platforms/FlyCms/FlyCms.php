<?php

namespace App\Contracts\Platforms\FlyCms;

use App\Contracts\Platforms\FlyCms\Managers\DomainManager;
use App\Contracts\Platforms\FlyCms\Managers\MenuManager;
use App\Contracts\Platforms\FlyCms\Managers\PageManager;
use App\Contracts\Platforms\FlyCms\Managers\PostManager;
use App\Contracts\Platforms\FlyCms\Managers\TagManager;
use App\Contracts\Platforms\FlyCms\Managers\ThemeManager;
use App\Contracts\Platforms\FlyCms\Managers\WebsiteManager;

interface FlyCms extends DomainManager, MenuManager, PageManager, PostManager, TagManager, ThemeManager, WebsiteManager
{
    public function setConfig(Config $config): static;

    public function getConfig(): ?Config;
}
