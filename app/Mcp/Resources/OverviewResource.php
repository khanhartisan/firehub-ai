<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\FlyCmsOverviewResource;
use App\Mcp\Support\Guidelines\McpResourceReference;
use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Mcp\Tools\ArticleTools\ListArticlesTool;
use App\Mcp\Tools\ArticleTools\ShowArticleTool;
use App\Mcp\Tools\ArticleTools\UpdateArticleContextTool;
use App\Mcp\Tools\AuthorTools\CreateAuthorTool;
use App\Mcp\Tools\AuthorTools\ListAuthorsTool;
use App\Mcp\Tools\AuthorTools\ShowAuthorTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorContextTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorTool;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\ChannelTools\GetChannelConfigSchemaTool;
use App\Mcp\Tools\ChannelTools\ListChannelsTool;
use App\Mcp\Tools\ChannelTools\ShowChannelTool;
use App\Mcp\Tools\ChannelTools\UpdateChannelTool;
use App\Mcp\Tools\ClientTools\CreateClientTool;
use App\Mcp\Tools\ClientTools\ListClientsTool;
use App\Mcp\Tools\ClientTools\ShowClientTool;
use App\Mcp\Tools\ClientTools\UpdateClientContextTool;
use App\Mcp\Tools\ClientTools\UpdateClientTool;
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

    /**
     * @return array<string, class-string<\App\Mcp\Tools\Tool>>
     */
    public static function relatedTools(): array
    {
        return [
            'list_clients' => ListClientsTool::class,
            'show_client' => ShowClientTool::class,
            'create_client' => CreateClientTool::class,
            'update_client' => UpdateClientTool::class,
            'update_client_context' => UpdateClientContextTool::class,
            'list_authors' => ListAuthorsTool::class,
            'show_author' => ShowAuthorTool::class,
            'create_author' => CreateAuthorTool::class,
            'update_author' => UpdateAuthorTool::class,
            'update_author_context' => UpdateAuthorContextTool::class,
            'list_articles' => ListArticlesTool::class,
            'show_article' => ShowArticleTool::class,
            'create_article' => CreateArticleTool::class,
            'update_article_context' => UpdateArticleContextTool::class,
            'list_channels' => ListChannelsTool::class,
            'show_channel' => ShowChannelTool::class,
            'create_channel' => CreateChannelTool::class,
            'update_channel' => UpdateChannelTool::class,
            'get_channel_config_schema' => GetChannelConfigSchemaTool::class,
            'list_platforms' => ListPlatformsTool::class,
            'create_platform' => CreatePlatformTool::class,
            'update_platform' => UpdatePlatformTool::class,
            'update_platform_config' => UpdatePlatformConfigTool::class,
        ];
    }

    private static function content(): string
    {
        $listClients = self::quotedTool('list_clients');
        $createClient = self::quotedTool('create_client');
        $updateClientContext = self::quotedTool('update_client_context');
        $createAuthor = self::quotedTool('create_author');
        $updateAuthorContext = self::quotedTool('update_author_context');
        $createArticle = self::quotedTool('create_article');
        $updateArticleContext = self::quotedTool('update_article_context');
        $showArticle = self::quotedTool('show_article');
        $listPlatforms = self::quotedTool('list_platforms');
        $createChannel = self::quotedTool('create_channel');
        $clientTools = self::quotedToolGroup('list_clients', 'show_client', 'create_client', 'update_client', 'update_client_context');
        $authorTools = self::quotedToolGroup('list_authors', 'show_author', 'create_author', 'update_author', 'update_author_context');
        $articleTools = self::quotedToolGroup('list_articles', 'show_article', 'create_article', 'update_article_context');
        $channelTools = self::quotedToolGroup('list_channels', 'show_channel', 'create_channel', 'update_channel', 'get_channel_config_schema');
        $createPlatform = self::quotedTool('create_platform');
        $updatePlatform = self::quotedTool('update_platform');
        $updatePlatformConfig = self::quotedTool('update_platform_config');
        $getChannelConfigSchema = self::quotedTool('get_channel_config_schema');

        $overviewUri = McpResourceReference::fromResourceClass(self::class)['uri'];
        $flyCmsOverviewUri = McpResourceReference::fromResourceClass(FlyCmsOverviewResource::class)['uri'];

        return <<<MARKDOWN
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

1. {$listClients} — find existing clients.
2. {$createClient} — create if needed.
3. {$updateClientContext} — set brand voice, industry, niches, guidelines.
4. {$createAuthor} + {$updateAuthorContext} — define writing personas.

### 2. Produce an article

1. {$createArticle} with `client_id`.
2. Optionally {$updateArticleContext} for article-specific guidance.
3. {$showArticle} — poll until `status` is `READY` (or handle `FAILED` / `ERROR`).
4. Use article content from the structured response when publishing to a platform.

### 3. Publish to a platform

1. {$listPlatforms} — pick a platform.
2. {$createChannel} — link client to platform.
3. Read `{$flyCmsOverviewUri}` (or the relevant platform overview resource) for provisioning and CMS operations.

## Tool naming

Tools use kebab-case names derived from their class name:

- Core tools: {$listClients}, {$createArticle}, etc.
- Platform-manager tools: prefixed with `platform-manager--{platform}--` (see platform overview resources for details).

Use `tools/list` or tool descriptions to discover the full catalog.

## Tool groups

### Clients

- {$clientTools}

### Authors

- {$authorTools}

### Articles

- {$articleTools}

### Channels

- {$channelTools}

### Platforms

- {$listPlatforms}, {$createPlatform} (super), {$updatePlatform} (super), {$updatePlatformConfig} (super)

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
| `{$overviewUri}` | This document |
| `{$flyCmsOverviewUri}` | FlyCMS setup, concepts, tools, and access rules |

## Practical tips

1. **Start with clients** — almost every workflow needs a `client_id`.
2. **Set context early** — client and author context materially affect article quality.
3. **Poll articles** — the build pipeline is asynchronous; use {$showArticle} to track progress.
4. **Use schemas** — call {$getChannelConfigSchema} and read tool input schemas before mutating config.
5. **Read platform overviews** — before CMS operations, read the relevant platform resource (e.g. `{$flyCmsOverviewUri}`).
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
