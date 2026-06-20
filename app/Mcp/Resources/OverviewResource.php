<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Title('MCP Server Overview')]
#[Description('High-level guide to this MCP server: domain model, workflows, tools, access rules, and related resources.')]
#[Uri('app://overview')]
#[MimeType('text/markdown')]
class OverviewResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(self::content());
    }

    private static function content(): string
    {
        return <<<'MARKDOWN'
# MCP Server Overview

Read this resource first when working with this MCP server. It explains what the server does, how data is organized, and which tools to use in common workflows.

## What this server is

This is a system for **content operations**. The MCP server exposes authenticated tools so AI agents can:

1. Manage **editorial tenants** (clients, authors, articles).
2. Configure **publishing channels** that connect a client to an external platform.
3. Manage platform content through a channel (see platform-specific resources).

The content production pipeline runs in the background and is **not** exposed through MCP. Focus on clients, articles, channels, and platform-manager tools.

## Authentication

- Endpoint: `/mcp/app` (Bearer token).
- Every tool requires an authenticated user.
- Access is scoped to clients the user belongs to, except where noted below.

## Domain model

```
User
 └── Client (brand / editorial tenant)
      ├── Authors (writing personas)
      ├── Articles (AI-built content)
      └── Channels (publishing destinations)
           └── Platform (e.g. flycms, others)
```

## Recommended workflows

### 1. Onboard a client

1. `list-clients-tool` — find existing clients.
2. `create-client-tool` — create if needed.
3. `update-client-context-tool` — set brand voice, industry, niches, guidelines.
4. `create-author-tool` + `update-author-context-tool` — define writing personas.

### 2. Produce an article

1. `create-article-tool` with `client_id`.
2. Optionally `update-article-context-tool` for article-specific guidance.
3. `show-article-tool` — poll until `status` is `READY` (or handle `FAILED` / `ERROR`).
4. Use article content from the structured response when publishing to a platform.

### 3. Publish to a platform

1. `list-platforms-tool` — pick a platform.
2. `create-channel-tool` — link client to platform.
3. Read the platform overview resource for provisioning and CMS operations.

## Tool naming

Tools use kebab-case names derived from their class name:

- Core tools: `list-clients-tool`, `create-article-tool`, etc.
- Platform-manager tools: prefixed with `platform-manager--{platform}--`, e.g. `platform-manager--flycms--create-page-tool`.

Use `tools/list` or tool descriptions to discover the full catalog.

## Tool groups

### Clients

- `list-clients-tool`, `show-client-tool`, `create-client-tool`, `update-client-tool`, `update-client-context-tool`

### Authors

- `list-authors-tool`, `show-author-tool`, `create-author-tool`, `update-author-tool`, `update-author-context-tool`

### Articles

- `list-articles-tool`, `show-article-tool`, `create-article-tool`, `update-article-context-tool`

### Channels

- `list-channels-tool`, `show-channel-tool`, `create-channel-tool`, `update-channel-tool`, `get-channel-config-schema-tool`

### Platforms

- `list-platforms-tool`, `create-platform-tool` (super), `update-platform-tool` (super), `update-platform-config-tool` (super)

## Pagination

List tools support pagination. Defaults:

- `per_page`: 500
- `page`: 1

Pass `per_page` and `page` when listing large collections.

## Responses

Tools return human-readable text plus **structured content** (JSON). Prefer structured fields for IDs and nested data; use text for status messages.

Errors are returned as tool errors with clear messages (e.g. missing access, invalid IDs).

## Access rules (summary)

| Scope | Rule |
|-------|------|
| Client | User must belong to the client |
| Author | Must be accessible by the user (via client) |
| Article | Must belong to an accessible client |
| Channel | Must belong to an accessible client |
| Platform write | Super user only (`is_super`) |

Platform-specific access rules are documented in each platform overview resource.

## MCP resources

| URI | Purpose |
|-----|---------|
| `app://overview` | This document |
| `platform-manager://flycms/overview` | FlyCMS setup, concepts, and access rules |
| `file://resources/website-guidelines-resource` | Editorial guidelines for FlyCMS website provisioning |
| `file://resources/page-guidelines-resource` | Editorial guidelines for FlyCMS pages |
| `file://resources/tag-guidelines-resource` | Editorial guidelines for FlyCMS tags |

## Practical tips

1. **Start with clients** — almost every workflow needs a `client_id`.
2. **Set context early** — client and author context materially affect article quality.
3. **Poll articles** — the build pipeline is asynchronous; use `show-article-tool` to track progress.
4. **Use schemas** — call `get-channel-config-schema-tool` and read tool input schemas before mutating config.
5. **Read platform overviews** — before CMS operations, read the relevant platform resource (e.g. FlyCMS).
MARKDOWN;
    }
}
