<?php

namespace App\Mcp\Resources\PlatformManagerResources\FlyCmsResources;

use App\Mcp\Resources\GuidelineResource;
use App\Mcp\Resources\OverviewResource as AppOverviewResource;
use App\Mcp\Resources\PublishingChannelsOverviewResource;
use App\Mcp\Support\Guidelines\GuidelinesBreadcrumb;
use App\Mcp\Support\Guidelines\McpResourceReference;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Title('FlyCMS Overview')]
#[Description('Guide to FlyCMS publishing via MCP: channel setup, website provisioning, CMS concepts, and access rules.')]
#[Uri('platform-manager://flycms/overview')]
#[MimeType('text/markdown')]
class OverviewResource extends FlyCmsResource implements GuidelineResource
{
    public function handle(Request $request): Response
    {
        return Response::text(self::content());
    }

    private static function content(): string
    {
        $appOverviewUri = McpResourceReference::fromResourceClass(AppOverviewResource::class)['uri'];
        $publishingUri = McpResourceReference::fromResourceClass(PublishingChannelsOverviewResource::class)['uri'];
        $websiteGuidelinesUri = McpResourceReference::fromResourceClass(WebsiteGuidelinesResource::class)['uri'];
        $pageGuidelinesUri = McpResourceReference::fromResourceClass(PageGuidelinesResource::class)['uri'];
        $tagGuidelinesUri = McpResourceReference::fromResourceClass(TagGuidelinesResource::class)['uri'];
        $menuGuidelinesUri = McpResourceReference::fromResourceClass(MenuGuidelinesResource::class)['uri'];
        $fileGuidelinesUri = McpResourceReference::fromResourceClass(FileGuidelinesResource::class)['uri'];

        $breadcrumb = GuidelinesBreadcrumb::render(
            [AppOverviewResource::class, PublishingChannelsOverviewResource::class],
            self::class,
        );

        return <<<MARKDOWN
{$breadcrumb}

# FlyCMS Overview

FlyCMS is one **platform type** in the publishing layer. This guide covers FlyCMS channels, website provisioning, and CMS management. For the big picture see `{$appOverviewUri}`; for channels and platforms in general see `{$publishingUri}`.

## How FlyCMS fits in

A **channel** is a client's representation on a platform. For FlyCMS, a channel belongs to a client and a `type = flycms` platform.

**`channel.reference` is the key prerequisite.** Before any CMS work, a FlyCMS **website must be created** and its ID stored in `channel.reference`. That reference links the channel to the live website — without it, FlyCMS tools will fail.

```
Channel (client + flycms platform)
 └── reference → FlyCMS Website ID  ← required; set when the website is created
      ├── Domains
      ├── Pages
      ├── Tags
      ├── Menus
      ├── Files
      └── Themes
```

Management tools use the `platform-manager--flycms--` prefix (see `{$publishingUri}` for the naming convention).

## Prerequisites

Before any FlyCMS tool:

1. A channel for the client on a `type = flycms` platform (list existing channels first; create one if none exists — see `{$publishingUri}`).
2. User access to the channel's client.
3. **A website created and referenced on the channel** — create a FlyCMS website, then set `channel.reference` to its website ID. This is mandatory; most FlyCMS tools require `channel_id` **and** a provisioned `channel.reference`, and will fail without both.

No `channel.reference` means no website is linked yet. Create and reference the website first (see `{$websiteGuidelinesUri}`), then proceed with CMS work.

Discover tools via `tools/list` with prefix `platform-manager--flycms--`.

## Recommended workflows

### Set up FlyCMS publishing

1. Pick a FlyCMS platform (see `{$publishingUri}`).
2. Find or create a channel for the client on that platform.
3. **Create a website and set `channel.reference`** — provision a FlyCMS website, then reference its ID on the channel. See `{$websiteGuidelinesUri}` for routes and theme setup.
4. Choose and apply a theme.

### Manage CMS content

After provisioning, manage entities via the channel (all require `channel_id`):

| Entity | Operations | Guidelines |
|--------|------------|------------|
| Website | view, update | `{$websiteGuidelinesUri}` |
| Domains | list, inspect | `{$websiteGuidelinesUri}` |
| Meta | list, upsert, delete | `{$websiteGuidelinesUri}` |
| Pages | CRUD | `{$pageGuidelinesUri}` |
| Tags | CRUD | `{$tagGuidelinesUri}` |
| Menus | CRUD | `{$menuGuidelinesUri}` |
| Files | upload, manage | `{$fileGuidelinesUri}` |
| Themes | browse during setup | `{$websiteGuidelinesUri}` |
| Posts | publish from hub | `{$publishingUri}`, `{$tagGuidelinesUri}` |

### Publish posts to FlyCMS

FlyCMS has **no direct post CRUD tools** in MCP. Posts are produced in the **content core**, then dispatched via **`publish_article`** on a FlyCMS channel (see `{$publishingUri}`). The hub creates FlyCMS posts in the background once the article is ready.

Typical flow:

1. Produce an article in the content core.
2. Set up tags on the website if posts should appear under tag archives (see `{$tagGuidelinesUri}`).
3. Call `publish_article` with the channel's `channel_id`.
4. Poll publication status via the hub; FlyCMS posts appear on the provisioned website.

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

1. **Create and reference the website first** — a FlyCMS website must exist and `channel.reference` must be set before any other CMS work.
2. **Always pass `channel_id`** — required on every FlyCMS tool.
3. **Read tool schemas** — mutation tools use structured payloads; inspect input schemas before calling.
4. **Pick a theme early** — apply during website setup, before pages.
5. **Tags before publishing** — create tag archives before calling `publish_article` (see `{$tagGuidelinesUri}`).
6. **Domains are read-only** — use domain list/show tools to verify hostnames (see `{$websiteGuidelinesUri}`).
MARKDOWN;
    }
}
