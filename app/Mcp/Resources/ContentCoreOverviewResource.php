<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\GuidelineResource;
use App\Mcp\Support\Guidelines\GuidelinesBreadcrumb;
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
use App\Mcp\Tools\ClientTools\CreateClientTool;
use App\Mcp\Tools\ClientTools\ListClientsTool;
use App\Mcp\Tools\ClientTools\ShowClientTool;
use App\Mcp\Tools\ClientTools\UpdateClientContextTool;
use App\Mcp\Tools\ClientTools\UpdateClientTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Title('Content Core Overview')]
#[Description('Guide to the content core: editorial tenants (clients, authors) and AI-produced articles — the internal layer that collects, synthesizes, and produces content.')]
#[Uri('app://content-core/overview')]
#[MimeType('text/markdown')]
class ContentCoreOverviewResource extends Resource implements GuidelineResource
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
        $clientTools = self::quotedToolGroup('list_clients', 'show_client', 'create_client', 'update_client', 'update_client_context');
        $authorTools = self::quotedToolGroup('list_authors', 'show_author', 'create_author', 'update_author', 'update_author_context');
        $articleTools = self::quotedToolGroup('list_articles', 'show_article', 'create_article', 'update_article_context');

        $overviewUri = McpResourceReference::fromResourceClass(OverviewResource::class)['uri'];
        $publishingUri = McpResourceReference::fromResourceClass(PublishingChannelsOverviewResource::class)['uri'];

        $breadcrumb = GuidelinesBreadcrumb::render(
            [OverviewResource::class],
            self::class,
        );

        return <<<MARKDOWN
{$breadcrumb}

# Content Core Overview

The **internal layer** of the hub. It hosts editorial tenants and all content, and it is where the system collects inputs, synthesizes, and produces content. Most work starts here. For the big picture see `{$overviewUri}`; to send content out, see `{$publishingUri}`.

## What lives here

- **Clients** — editorial tenants (brands). The root of almost every workflow; most tools need a `client_id`.
- **Authors** — writing personas owned by a client. Their context shapes tone and style.
- **Articles** — AI-produced content for a client, built by a background pipeline.

## Domain model

Users and clients are **many-to-many**: a user can access several clients, and a client can have several users. Everything else hangs off the client.

```
Users ←──many-to-many──→ Client (brand / editorial tenant)
                          ├── Authors (writing personas)
                          └── Articles (AI-produced content)
```

## The build pipeline

Articles are produced by a background pipeline that collects source material and synthesizes the final content. This pipeline is **not** exposed via MCP — you create an article, then poll it until it is ready.

- {$createArticle} kicks off production for a `client_id`.
- {$showArticle} reports `status`; poll until `READY` (or handle `FAILED` / `ERROR`).
- Client and author context feed the pipeline, so set them early for better output.

## Recommended workflows

### 1. Onboard a client

1. {$listClients} — find existing clients.
2. {$createClient} — create one if needed.
3. {$updateClientContext} — brand voice, industry, niches, guidelines.
4. {$createAuthor} + {$updateAuthorContext} — set up writing personas.

### 2. Produce an article

1. {$createArticle} with `client_id`.
2. Optionally {$updateArticleContext} for article-specific guidance.
3. {$showArticle} — poll until `status` is `READY` (or handle `FAILED` / `ERROR`).
4. Hand the structured content to a publishing channel (see `{$publishingUri}`).

## Tool groups

### Clients

Editorial tenants (brands).

- {$clientTools}

### Authors

Writing personas for a client.

- {$authorTools}

### Articles

AI-produced content for a client.

- {$articleTools}

## Access rules

| Scope | Rule |
|-------|------|
| Client | User must belong to the client |
| Author | Accessible via its client |
| Article | Belongs to an accessible client |

## Practical tips

1. **Clients first** — most workflows need a `client_id`.
2. **Set context early** — client and author context drive article quality.
3. **Poll articles** — production is async; use {$showArticle}.
4. **Then publish** — once an article is `READY`, move to `{$publishingUri}`.
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
