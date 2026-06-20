<?php

namespace App\Mcp\Resources\PlatformManagerResources\FlyCmsResources;

use App\Mcp\Resources\OverviewResource;
use App\Mcp\Support\Guidelines\McpResourceReference;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Title('FlyCMS Platform Overview')]
#[Description('Guide to FlyCMS publishing via MCP: channel setup, website provisioning, CMS concepts, and access rules.')]
#[Uri('platform-manager://flycms/overview')]
#[MimeType('text/markdown')]
class FlyCmsOverviewResource extends FlyCmsResource
{
    public function handle(Request $request): Response
    {
        return Response::text(self::content());
    }

    private static function content(): string
    {
        $overviewUri = McpResourceReference::fromResourceClass(OverviewResource::class)['uri'];
        $websiteGuidelinesUri = McpResourceReference::fromResourceClass(WebsiteGuidelinesResource::class)['uri'];
        $pageGuidelinesUri = McpResourceReference::fromResourceClass(PageGuidelinesResource::class)['uri'];
        $tagGuidelinesUri = McpResourceReference::fromResourceClass(TagGuidelinesResource::class)['uri'];
        $menuGuidelinesUri = McpResourceReference::fromResourceClass(MenuGuidelinesResource::class)['uri'];
        $fileGuidelinesUri = McpResourceReference::fromResourceClass(FileGuidelinesResource::class)['uri'];

        return <<<MARKDOWN
# FlyCMS Platform Overview

FlyCMS channels and CMS content. For clients, articles, and channels, start with `{$overviewUri}`.

## How FlyCMS fits in

A **channel** links a client to a FlyCMS **platform**. After provisioning, `channel.reference` holds the FlyCMS **website ID**.

```
Channel (client + flycms platform)
 └── reference → FlyCMS Website ID
      ├── Domains
      ├── Pages
      ├── Tags
      ├── Menus
      ├── Files
      └── Themes
```

## Prerequisites

Before FlyCMS tools:

1. Channel linking the client to a `type = flycms` platform.
2. User access to the channel's client.
3. Provisioned website (`channel.reference` set).

Most tools require `channel_id` and fail without a provisioned website. Discover tools via `tools/list` (prefix `platform-manager--flycms--`).

## Recommended workflows

### Set up FlyCMS publishing

1. Pick a FlyCMS platform.
2. Create a channel for the client.
3. Provision a website (sets `channel.reference`). See `{$websiteGuidelinesUri}` for routes and theme setup.
4. Choose and apply a theme.

### Manage CMS content

After provisioning, manage entities via the channel (all require `channel_id`):

| Entity | Operations | Guidelines |
|--------|------------|------------|
| Website | view, update | `{$websiteGuidelinesUri}` |
| Domains | list, inspect | — |
| Pages | CRUD | `{$pageGuidelinesUri}` |
| Tags | CRUD | `{$tagGuidelinesUri}` |
| Menus | CRUD | `{$menuGuidelinesUri}` |
| Files | upload, manage | `{$fileGuidelinesUri}` |
| Themes | browse during setup | `{$websiteGuidelinesUri}` |

## Access rules

| Scope | Rule |
|-------|------|
| Channel | Client accessible to user |
| Platform type | Must be `flycms` |
| Website | `channel.reference` must be set |
| Website-scoped resources | Tags, menus, pages, domains belong to channel website |
| Files | Belong to user's FlyCMS user scope |

## Authentication model

Tools act as the authenticated MCP user. The server provisions a per-user FlyCMS API key on first use; file operations are scoped to that user.

## Practical tips

1. **Provision first** — create the website before other CMS work.
2. **Always pass `channel_id`** — required on every FlyCMS tool.
3. **Read tool schemas** — mutation tools use structured payloads; inspect input schemas before calling.
4. **Pick a theme early** — apply during website setup, before pages.
MARKDOWN;
    }
}
