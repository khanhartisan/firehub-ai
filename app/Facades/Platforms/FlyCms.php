<?php

namespace App\Facades\Platforms;

use App\Services\Platforms\FlyCms\FlyCmsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\Platforms\FlyCms\FlyCms setConfig(\App\Contracts\Platforms\FlyCms\Config $config)
 * @method static \App\Contracts\Platforms\FlyCms\Config|null getConfig()
 * @method static \App\Contracts\Platforms\FlyCms\Resources\WebsiteResource|null showWebsite(string $websiteId)
 * @method static \App\Contracts\Platforms\FlyCms\Resources\WebsiteResource createWebsite(\App\Contracts\Platforms\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData $createWebsiteData)
 * @method static \App\Contracts\Platforms\FlyCms\Resources\WebsiteResource updateWebsite(string $websiteId, \App\Contracts\Platforms\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData $updateWebsiteData)
 * @method static \App\Contracts\Platforms\FlyCms\Resources\WebsiteResource[] listWebsites(int $page = 1, int $limit = 100, \App\Contracts\Platforms\FlyCms\Filters\WebsiteFilter|null $websiteFilter = null)
 * @method static bool deleteWebsite(string $websiteId)
 * @method static \App\Contracts\Platforms\FlyCms\FlyCms driver(string|null $driver = null)
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
