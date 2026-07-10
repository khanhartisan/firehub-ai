<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\GuidelineResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\OverviewResource as FlyCmsOverviewResource;
use App\Mcp\Support\Guidelines\GuidelinesBreadcrumb;
use App\Mcp\Support\Guidelines\McpResourceReference;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Title('MCP Server Overview')]
#[Description('High-level guide to this MCP server: what the application is, its two main parts, and where to go next.')]
#[Uri('app://overview')]
#[MimeType('text/markdown')]
class OverviewResource extends Resource implements GuidelineResource
{
    public function handle(Request $request): Response
    {
        return Response::text(self::content());
    }

    private static function content(): string
    {
        $contentCoreUri = McpResourceReference::fromResourceClass(ContentCoreOverviewResource::class)['uri'];
        $publishingUri = McpResourceReference::fromResourceClass(PublishingChannelsOverviewResource::class)['uri'];
        $flyCmsOverviewUri = McpResourceReference::fromResourceClass(FlyCmsOverviewResource::class)['uri'];

        $breadcrumb = GuidelinesBreadcrumb::render([], self::class);

        return <<<MARKDOWN
{$breadcrumb}

# MCP Server Overview

Start here. This is the map of the application and points to the deeper guides.

## What this application is

A **central hub for mass content management**. It collects source material, synthesizes and produces content at scale, and dispatches it to many publishing channels — while managing those channels and their destinations from one place. Think of it as the control center where editorial tenants, AI-produced articles, and external publishing platforms all come together.

## Two main parts

The server is split into two layers. Read the overview for the layer you are working in:

### 1. Content core — `{$contentCoreUri}`

The **internal layer**. It hosts editorial tenants (clients, authors) and all content, and it is responsible for collecting, synthesizing, and producing articles. Most work starts here.

### 2. Publishing channels — `{$publishingUri}`

The **remote layer**. Publishing channels connect each client to external platforms (e.g. FlyCMS) and dispatch produced content to live destinations. Platform-specific tools run through channels.

```
Central hub
 ├── Content core        → clients, authors, articles (collect · synthesize · produce)
 └── Publishing channels → platforms, channels        (dispatch · manage destinations)
```

## Authentication

- Endpoint: `/mcp/app` (Bearer token).
- All tools require an authenticated user.
- Access is scoped to the user's clients unless a tool notes otherwise.

## Conventions

- **Tool naming** — kebab-case from class names. Platform-manager tools use a `platform-manager--{platform}--` prefix. Use `tools/list` or tool descriptions for the full catalog.
- **Pagination** — list tools paginate (`per_page` default 500, `page` default 1). Pass both for large collections.
- **Responses** — tools return text plus **structured JSON**. Prefer structured fields for IDs and nested data; use text for status. Errors are tool errors with clear messages.

## Where to go next

1. Producing or managing content? Read `{$contentCoreUri}`.
2. Connecting platforms or publishing? Read `{$publishingUri}`.
3. Working with FlyCMS specifically? Read `{$flyCmsOverviewUri}`.
MARKDOWN;
    }
}
