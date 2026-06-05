<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\FlyCms;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\CreateUserData;
use App\Contracts\PlatformManager\FlyCms\Resources\DomainResource;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use App\Contracts\PlatformManager\FlyCms\Resources\PageResource;
use App\Contracts\PlatformManager\FlyCms\Resources\RoleResource;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use App\Enums\PlatformType;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Tools\PlatformManagerTools\PlatformManagerTool;
use App\Mcp\Tools\Tool;
use App\Models\Channel;
use App\Models\Platform;
use App\Models\User;
use App\Utils\Json;
use App\Utils\Str;

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

    /**
     * @param Channel $channel
     * @param User|null $user If null the FlyCms will use the master key, otherwise it will use the user's key
     * @return FlyCms
     */
    public function getFlyCmsManager(Channel $channel, ?User $user = null): FlyCms
    {
        /** @var Platform $platform */
        $platform = $channel->platform;

        $flycms = \App\Facades\Platforms\FlyCms::driver();

        /** @var Config $platformConfig */
        if ($platformConfig = $platform->config) {
            $flycms->setConfig($platformConfig);
        }

        if ($user) {
            $userDataMetaKey = 'user-'.$user->id;

            // Try to get user data from platform's meta
            if ($flyCmsUserData = $platform->getMetaValue($userDataMetaKey)) {
                try {
                    $flyCmsUserData = Json::decode($flyCmsUserData, true);
                    if (!is_array($flyCmsUserData)
                        or !isset($flyCmsUserData['id'])
                        or !isset($flyCmsUserData['api_key'])
                    ) {
                        $flyCmsUserData = null;
                    }
                } catch (\Exception) {
                    $flyCmsUserData = null;
                }
            }

            // FlyCms user data was not found, create new user
            if (!$flyCmsUserData) {
                $flyCmsUserResource = $flycms->createUser(
                    new CreateUserData()
                        ->setData([
                            'name' => $user->id,
                            'email' => $user->email,
                            'password' => Str::random(8),
                            'api_key' => $apiKey = sha1(Str::random())
                        ])
                );

                $flyCmsUserData = [
                    'id' => $flyCmsUserResource->get('id'),
                    'api_key' => $apiKey
                ];

                // Save user data to meta
                $platform->putMeta($userDataMetaKey, Json::encode($flyCmsUserData));
            }

            // Now return a new flycms manager instance as a user
            /** @var Config $userFlyCmsConfig */
            $userFlyCmsConfig = $platformConfig->clone();
            $userFlyCmsConfig->setApiKey($flyCmsUserData['api_key']);

            /** @var FlyCms $userFlyCms */
            $userFlyCms = $flycms->clone();
            $userFlyCms->setConfig($userFlyCmsConfig);

            return $userFlyCms;
        }

        return $flycms;
    }

    protected function requireFlyCmsWebsiteId(Channel $channel, bool $throwException = true): ?string
    {
        if (! $flycmsWebsiteId = $channel->reference) {
            if ($throwException) {
                throw new McpToolException('Channel '.$channel->id.' does not have a FlyCMS website reference.');
            }
            return null;
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

    protected function resolveDomainForChannel(Channel $channel, string $domainId): DomainResource
    {
        $flycms = $this->getFlyCmsManager($channel);
        $websiteId = $this->requireFlyCmsWebsiteId($channel);

        if (! $domain = $flycms->showDomain($domainId)) {
            throw new McpToolException("Domain [{$domainId}] not found.");
        }

        if (($domain->get('website_id') ?? null) !== $websiteId) {
            throw new McpToolException("Domain [{$domainId}] not found.");
        }

        return $domain;
    }
}