<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\FlyCmsOverviewResource;
use App\Mcp\Support\Guidelines\McpResourceReference;
use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\ArticleTools\PublishArticleTool;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\ChannelTools\GetChannelConfigSchemaTool;
use App\Mcp\Tools\ChannelTools\ListChannelsTool;
use App\Mcp\Tools\ChannelTools\ShowChannelTool;
use App\Mcp\Tools\ChannelTools\UpdateChannelTool;
use App\Mcp\Tools\PlatformTools\CreatePlatformTool;
use App\Mcp\Tools\PlatformTools\ListPlatformsTool;
use App\Mcp\Tools\PlatformTools\UpdatePlatformConfigTool;
use App\Mcp\Tools\PlatformTools\UpdatePlatformTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Title('Publishing Channels Overview')]
#[Description('Guide to the publishing layer: platforms and channels that dispatch produced content to external destinations (e.g. FlyCMS).')]
#[Uri('app://publishing-channels/overview')]
#[MimeType('text/markdown')]
class PublishingChannelsOverviewResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(self::content());
    }

    /**
     * @return array<string, class-string<\App\Mcp\Tools\Tool>>
     */
    public static function relatedTools(): array
    {
        return [
            'list_channels' => ListChannelsTool::class,
            'show_channel' => ShowChannelTool::class,
            'create_channel' => CreateChannelTool::class,
            'update_channel' => UpdateChannelTool::class,
            'get_channel_config_schema' => GetChannelConfigSchemaTool::class,
            'publish_article' => PublishArticleTool::class,
            'list_platforms' => ListPlatformsTool::class,
            'create_platform' => CreatePlatformTool::class,
            'update_platform' => UpdatePlatformTool::class,
            'update_platform_config' => UpdatePlatformConfigTool::class,
        ];
    }

    private static function content(): string
    {
        $listPlatforms = self::quotedTool('list_platforms');
        $listChannels = self::quotedTool('list_channels');
        $showChannel = self::quotedTool('show_channel');
        $createChannel = self::quotedTool('create_channel');
        $publishArticle = self::quotedTool('publish_article');
        $getChannelConfigSchema = self::quotedTool('get_channel_config_schema');
        $channelTools = self::quotedToolGroup('list_channels', 'show_channel', 'create_channel', 'update_channel', 'get_channel_config_schema');
        $createPlatform = self::quotedTool('create_platform');
        $updatePlatform = self::quotedTool('update_platform');
        $updatePlatformConfig = self::quotedTool('update_platform_config');

        $overviewUri = McpResourceReference::fromResourceClass(OverviewResource::class)['uri'];
        $contentCoreUri = McpResourceReference::fromResourceClass(ContentCoreOverviewResource::class)['uri'];
        $flyCmsOverviewUri = McpResourceReference::fromResourceClass(FlyCmsOverviewResource::class)['uri'];

        return <<<MARKDOWN
# Publishing Channels Overview

The **remote layer** of the hub. It dispatches produced content to external destinations and manages the connections that make that possible. For the big picture see `{$overviewUri}`; for the content that gets dispatched, see `{$contentCoreUri}`.

## What lives here

- **Platforms** — external publishing backends the hub can talk to (e.g. Facebook, FlyCMS). Managed centrally; write access is super-user only.
- **Channels** — the representation of a client on a platform. A channel belongs to one client **and** one platform, and carries the config needed to publish there.

## Domain model

A **client** and a **platform** are peers — neither owns the other. A **channel** sits between them: it belongs to a client and to a platform, and represents that client's presence on that platform.

> Example: **BBC News** is a client and **Facebook** is a platform. The **BBC News fanpage** on Facebook is a channel — BBC News's representation on the Facebook platform.

```
Client (from the content core)          Platform (e.g. facebook, flycms)
        │                                        │
        └───────────────┬────────────────────────┘
                        │
                     Channel  (client's representation on the platform)
                        └── reference → remote resource (e.g. Facebook page, FlyCMS website)
```

A client can have many channels (one per platform, or more), and a platform can host many channels (one per client).

## How dispatch works

1. A **platform** describes an integration the hub supports.
2. A **channel** connects a client to that platform and holds its configuration.
3. Once the channel is provisioned, {$publishArticle} sends an article to one or more channels. Publishing runs in the background; an article that is not yet `READY` is published automatically once it becomes ready.

## Platform types & management tools

There are **many types of platforms** (e.g. `facebook`, `youtube`, `flycms`, …), and each type behaves differently. Because of that, **every platform type exposes its own set of management tools** — the operations that make sense for a CMS (pages, tags, menus) are not the same as those for a social channel (posts, videos, playlists). A platform's `type` decides which management toolset applies to its channels.

The generic tools below ({$listPlatforms}, {$channelTools}, {$publishArticle}) work across **all** platform types. Type-specific management tools are additional and discovered per type.

### Tool naming convention

Platform-management tools follow a predictable, kebab-case pattern:

```
platform-manager--{platform_type}--{action}
```

- `platform-manager` — shared prefix for every platform-management tool.
- `{platform_type}` — the platform's type (e.g. `flycms`, `facebook`, `youtube`).
- `{action}` — the operation (e.g. `create-page`, `list-tags`).

For example, FlyCMS page and tag tools are named `platform-manager--flycms--create-page`, `platform-manager--flycms--list-tags`, and so on. To discover the tools for a given type, call `tools/list` and filter by the `platform-manager--{platform_type}--` prefix, then read that platform's overview for details.

## Recommended workflows

### 1. Connect a client to a platform

1. {$listPlatforms} — pick a platform.
2. {$listChannels} for the client — look for an existing channel on that platform.
   - **Channel found** — select it and use {$showChannel} to inspect its config and provisioning state.
   - **No channel found** — {$getChannelConfigSchema} to see what config is required, then {$createChannel} to create the channel for that client and platform.
3. Read the platform overview for provisioning steps (e.g. `{$flyCmsOverviewUri}`).

### 2. Publish content

1. Ensure the article is produced (see `{$contentCoreUri}`).
2. Ensure the target channel is provisioned (`channel.reference` set).
3. {$publishArticle} with `client_id`, `article_id`, and one or more `channel_ids`.

## Tool groups

### Platforms

External publishing backends (e.g. FlyCMS).

- {$listPlatforms}, {$createPlatform} (super), {$updatePlatform} (super), {$updatePlatformConfig} (super)

### Channels

A client's publishing channel on a platform.

- {$channelTools}

### Publishing

Dispatch produced content to channels.

- {$publishArticle}

## Platform overviews

Each platform **type** has its own overview describing its concepts, provisioning steps, and management tools. Read the overview for the type you are working with:

- FlyCMS (`flycms`) — `{$flyCmsOverviewUri}`

More platform types are added over time; use `tools/list` to see which management toolsets are currently available.

## Access rules

| Scope | Rule |
|-------|------|
| Channel | Belongs to an accessible client |
| Platform write | Super user only (`is_super`) |
| Publishing | Channels must belong to the article's client and be provisioned |

Platform-specific rules live in each platform overview.

## Practical tips

1. **Read schemas** — call {$getChannelConfigSchema} before creating or editing a channel.
2. **Provision before publishing** — a channel needs `channel.reference` set (see the platform overview).
3. **Read platform overviews** — before CMS work (e.g. `{$flyCmsOverviewUri}`).
4. **Publishing is async** — {$publishArticle} can be called before an article is ready; it dispatches automatically once ready.
MARKDOWN;
    }

    private static function quotedTool(string $key): string
    {
        return McpToolName::quoted(self::relatedTools()[$key]);
    }

    private static function quotedToolGroup(string ...$keys): string
    {
        return implode(', ', array_map(self::quotedTool(...), $keys));
    }
}
