<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Filters\RoleFilter;
use App\Contracts\PlatformManager\FlyCms\FlyCms;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\CreateRoleData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\CreateUserData;
use App\Contracts\PlatformManager\FlyCms\Resources\DomainResource;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
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

        $flycms = \App\Facades\Platforms\FlyCms::driver('flycms');

        /** @var Config $platformConfig */
        if ($platformConfig = $platform->config) {
            $flycms->setConfig($platformConfig);
        }

        if ($user) {
            $flyCmsUserData = $this->getFlyCmsUserData($channel, $user);

            /** @var FlyCms $userFlyCms */
            $userFlyCms = $flycms->clone();

            /** @var Config $userFlyCmsConfig */
            $userFlyCmsConfig = $platformConfig instanceof Config
                ? $platformConfig->clone()
                : ($flycms->getConfig()?->clone() ?? $flycms->makeConfig());

            $userFlyCmsConfig->setApiKey($flyCmsUserData['api_key']);
            $userFlyCms->setConfig($userFlyCmsConfig);

            return $userFlyCms;
        }

        return $flycms;
    }

    /**
     * @return array{id: string, api_key: string}
     */
    protected function getFlyCmsUserData(Channel $channel, User $user): array
    {
        /** @var Platform $platform */
        $platform = $channel->platform;
        $userDataMetaKey = 'user-'.$user->id;

        if ($flyCmsUserData = $platform->getMetaValue($userDataMetaKey)) {
            try {
                $flyCmsUserData = Json::decode($flyCmsUserData, true);
                if (! is_array($flyCmsUserData)
                    or ! isset($flyCmsUserData['id'])
                    or ! isset($flyCmsUserData['api_key'])
                ) {
                    $flyCmsUserData = null;
                }
            } catch (\Exception) {
                $flyCmsUserData = null;
            }
        }

        if (! $flyCmsUserData) {
            $flycms = $this->getFlyCmsManager($channel);

            $flyCmsUserResource = $flycms->createUser(
                new CreateUserData()
                    ->setData([
                        'role_id' => $this->getFlyCmsUserRoleId($channel),
                        'name' => $user->id,
                        'email' => $user->email,
                        'password' => Str::random(8),
                        'api_key' => $apiKey = sha1(Str::random()),
                    ])
            );

            $flyCmsUserData = [
                'id' => $flyCmsUserResource->get('id'),
                'api_key' => $apiKey,
            ];

            $platform->putMeta($userDataMetaKey, Json::encode($flyCmsUserData));
        }

        return $flyCmsUserData;
    }

    protected function getFlyCmsUserId(Channel $channel, User $user): string
    {
        return $this->getFlyCmsUserData($channel, $user)['id'];
    }

    protected function getFlyCmsUserRoleId(Channel $channel): string
    {
        /** @var Platform $platform */
        $platform = $channel->platform;

        $roleIdMetaKey = 'user-role-id';

        // Return role id if saved
        if ($roleId = $platform->getMetaValue($roleIdMetaKey)) {
            return $roleId;
        }

        $flycms = $this->getFlyCmsManager($channel);

        // Search for an existing role
        $roles = $flycms->listRoles(1, 1, new RoleFilter()->setFilterData([
            'search' => $platform->id,
        ]));

        // Return the existing role if found
        if ($roles and $role = $roles[0]) {
            $platform->putMeta($roleIdMetaKey, $roleId = $role->get('id'));
            return $roleId;
        }

        // Create a new role
        $newRole = $flycms->createRole(new CreateRoleData()->setData([
            'name' => $platform->id,
            'abilities' => ['*']
        ]));

        $platform->putMeta($roleIdMetaKey, $roleId = $newRole->get('id'));
        return $roleId;
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

    protected function resolveTagForChannel(Channel $channel, User $user, string $tagId): TagResource
    {
        $flycms = $this->getFlyCmsManager($channel, $user);
        $websiteId = $this->requireFlyCmsWebsiteId($channel);

        if (! $tag = $flycms->showTag($tagId)) {
            throw new McpToolException("Tag [{$tagId}] not found.");
        }

        if (($tag->get('website_id') ?? null) !== $websiteId) {
            throw new McpToolException("Tag [{$tagId}] not found.");
        }

        return $tag;
    }

    protected function resolveMenuForChannel(Channel $channel, User $user, string $menuId): MenuResource
    {
        $flycms = $this->getFlyCmsManager($channel, $user);
        $websiteId = $this->requireFlyCmsWebsiteId($channel);

        if (! $menu = $flycms->showMenu($menuId)) {
            throw new McpToolException("Menu [{$menuId}] not found.");
        }

        if (($menu->get('website_id') ?? null) !== $websiteId) {
            throw new McpToolException("Menu [{$menuId}] not found.");
        }

        return $menu;
    }

    protected function resolvePageForChannel(Channel $channel, User $user, string $pageId): PageResource
    {
        $flycms = $this->getFlyCmsManager($channel, $user);
        $websiteId = $this->requireFlyCmsWebsiteId($channel);

        if (! $page = $flycms->showPage($pageId)) {
            throw new McpToolException("Page [{$pageId}] not found.");
        }

        if (($page->get('website_id') ?? null) !== $websiteId) {
            throw new McpToolException("Page [{$pageId}] not found.");
        }

        return $page;
    }

    protected function resolveDomainForChannel(Channel $channel, User $user, string $domainId): DomainResource
    {
        $flycms = $this->getFlyCmsManager($channel, $user);
        $websiteId = $this->requireFlyCmsWebsiteId($channel);

        if (! $domain = $flycms->showDomain($domainId)) {
            throw new McpToolException("Domain [{$domainId}] not found.");
        }

        if (($domain->get('website_id') ?? null) !== $websiteId) {
            throw new McpToolException("Domain [{$domainId}] not found.");
        }

        return $domain;
    }

    protected function resolveFileForChannel(Channel $channel, User $user, string $fileId): FileResource
    {
        $flycms = $this->getFlyCmsManager($channel, $user);
        $flycmsUserId = $this->getFlyCmsUserId($channel, $user);

        if (! $file = $flycms->showFile($fileId)) {
            throw new McpToolException("File [{$fileId}] not found.");
        }

        if (($file->get('user_id') ?? null) !== $flycmsUserId) {
            throw new McpToolException("File [{$fileId}] not found.");
        }

        return $file;
    }
}