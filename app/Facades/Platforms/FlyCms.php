<?php

namespace App\Facades\Platforms;

use App\Services\PlatformManager\FlyCms\FlyCmsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\PlatformManager\FlyCms\FlyCms setConfig(\App\Contracts\PlatformManager\FlyCms\Config $config)
 * @method static \App\Contracts\PlatformManager\FlyCms\Config|null getConfig()
 * @method static \App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource|null showWebsite(string $websiteId)
 * @method static \App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource createWebsite(\App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData $createWebsiteData)
 * @method static \App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource updateWebsite(string $websiteId, \App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData $updateWebsiteData)
 * @method static \App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource[] listWebsites(int $page = 1, int $limit = 100, \App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter|null $websiteFilter = null)
 * @method static bool deleteWebsite(string $websiteId)
 * @method static \App\Contracts\PlatformManager\FlyCms\FlyCms driver(string|null $driver = null)
 *
 * @see FlyCmsManager
 */
class FlyCms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'flycms.manager';
    }
}
