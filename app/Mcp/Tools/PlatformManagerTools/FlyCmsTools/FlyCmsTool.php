<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools;

use App\Contracts\PlatformManager\FlyCms\FlyCms;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use App\Contracts\PlatformManager\FlyCms\Resources\PageResource;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use App\Enums\PlatformType;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Tools\PlatformManagerTools\PlatformManagerTool;
use App\Mcp\Tools\Tool;
use App\Models\Channel;
use App\Models\Platform;

abstract class FlyCmsTool extends PlatformManagerTool
{
    protected function namePrefix(): ?string
    {
        return parent::namePrefix().'--flycms--';
    }

    protected function validateChannel(Channel $channel): void
    {
        if (!$platform = $channel->platform or $platform->type !== PlatformType::FLYCMS) {
            throw new McpToolException('Channel '.$channel->id.' is not supported by flycms manager.');
        }
    }

    public function getFlyCmsManager(Channel $channel): FlyCms
    {
        /** @var Platform $platform */
        $platform = $channel->platform;

        $flycms = \App\Facades\Platforms\FlyCms::driver();
        if ($platformConfig = $platform->config) {
            $flycms->setConfig($platformConfig);
        }

        return $flycms;
    }

    protected function requireFlyCmsWebsiteId(Channel $channel): string
    {
        if (! $flycmsWebsiteId = $channel->reference) {
            throw new McpToolException('Channel '.$channel->id.' does not have a FlyCMS website reference.');
        }

        return $flycmsWebsiteId;
    }

    protected function resolveTagForChannel(Channel $channel, string $tagId): TagResource
    {
        $flycms = $this->getFlyCmsManager($channel);
        $websiteId = $this->requireFlyCmsWebsiteId($channel);

        if (! $tag = $flycms->showTag($tagId)) {
            throw new McpToolException("Tag [{$tagId}] not found.");
        }

        if (($tag->get('website_id') ?? null) !== $websiteId) {
            throw new McpToolException("Tag [{$tagId}] not found.");
        }

        return $tag;
    }

    protected function resolveMenuForChannel(Channel $channel, string $menuId): MenuResource
    {
        $flycms = $this->getFlyCmsManager($channel);
        $websiteId = $this->requireFlyCmsWebsiteId($channel);

        if (! $menu = $flycms->showMenu($menuId)) {
            throw new McpToolException("Menu [{$menuId}] not found.");
        }

        if (($menu->get('website_id') ?? null) !== $websiteId) {
            throw new McpToolException("Menu [{$menuId}] not found.");
        }

        return $menu;
    }

    protected function resolvePageForChannel(Channel $channel, string $pageId): PageResource
    {
        $flycms = $this->getFlyCmsManager($channel);
        $websiteId = $this->requireFlyCmsWebsiteId($channel);

        if (! $page = $flycms->showPage($pageId)) {
            throw new McpToolException("Page [{$pageId}] not found.");
        }

        if (($page->get('website_id') ?? null) !== $websiteId) {
            throw new McpToolException("Page [{$pageId}] not found.");
        }

        return $page;
    }
}