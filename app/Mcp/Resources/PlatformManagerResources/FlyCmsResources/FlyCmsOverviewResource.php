<?php

namespace App\Mcp\Resources\PlatformManagerResources\FlyCmsResources;

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
        return <<<'MARKDOWN'
# FlyCMS Platform Overview

Read this resource when working with FlyCMS channels and CMS content. For the general MCP domain model (clients, articles, channels), start with `app://overview`.

## How FlyCMS fits in

A **channel** links a client to a FlyCMS **platform**. Once provisioned, the channel's `reference` field stores the FlyCMS **website ID**.

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

Before using FlyCMS tools, ensure:

1. A channel exists linking the client to a platform with `type = flycms`.
2. The user has access to the channel's client.
3. A website is provisioned on the channel, which sets `channel.reference` to the FlyCMS website ID.

Most FlyCMS tools require `channel_id` and will fail if the website is not provisioned. Use `tools/list` to discover available FlyCMS tools (prefixed `platform-manager--flycms--`).

## Recommended workflows

### Set up FlyCMS publishing

1. Pick a FlyCMS platform.
2. Create a channel linking the client to that platform.
3. Provision a website on the channel (stores the website ID in `channel.reference`).
4. Choose a theme and apply it to the website.

### Manage CMS content

Once the website is provisioned, manage FlyCMS entities through the channel:

- **Website** — view or update site settings
- **Domains** — list or inspect domains attached to the website
- **Pages** — create, update, list, or delete content pages
- **Tags** — manage taxonomy labels (see tag guidelines resource for editorial rules)
- **Menus** — manage navigation structures
- **Files** — upload and manage media assets
- **Themes** — browse available themes during setup

All CMS operations require `channel_id`.

For tag-specific editorial rules, read `file://resources/tag-guidelines-resource`.

## Access rules

| Scope | Rule |
|-------|------|
| Channel | Must belong to a client the user can access |
| Platform type | Channel platform must be `flycms` |
| Website | `channel.reference` must be set (website provisioned) |
| Website-scoped resources | Tags, menus, pages, domains must belong to the channel's website |
| Files | Must belong to the user's FlyCMS user scope on the platform |

## Authentication model

FlyCMS tools act on behalf of the authenticated MCP user. The server provisions a per-user FlyCMS API key on first use and scopes file operations to that user.

## Practical tips

1. **Provision first** — create the website on a new channel before other CMS operations.
2. **Always pass `channel_id`** — it is required on every FlyCMS tool.
3. **Read tool schemas** — mutation tools accept structured payloads; inspect each tool's input schema before calling.
4. **Use themes early** — pick and apply a theme during website setup before creating pages.
MARKDOWN;
    }
}
