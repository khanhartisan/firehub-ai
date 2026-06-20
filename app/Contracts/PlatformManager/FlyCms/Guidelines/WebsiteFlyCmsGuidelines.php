<?php

namespace App\Contracts\PlatformManager\FlyCms\Guidelines;

use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\ProvidesFlyCmsGuidelines;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools\ListThemesTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools\ShowThemeTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\CreateWebsiteTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\ShowWebsiteTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\UpdateWebsiteTool;

class WebsiteFlyCmsGuidelines implements ProvidesFlyCmsGuidelines
{
    public static function title(): string
    {
        return 'FlyCMS Website Guidelines';
    }

    public static function relatedTools(): array
    {
        return [
            'create' => CreateWebsiteTool::class,
            'update' => UpdateWebsiteTool::class,
            'show' => ShowWebsiteTool::class,
            'list_themes' => ListThemesTool::class,
            'show_theme' => ShowThemeTool::class,
        ];
    }

    public static function intro(): string
    {
        $relatedTools = static::relatedTools();

        return sprintf(
            'Read this resource before provisioning or updating a FlyCMS website with %s or %s.',
            McpToolName::quoted($relatedTools['create']),
            McpToolName::quoted($relatedTools['update']),
        )."\n\n"
        .'This is the **first CMS step** on a FlyCMS channel. A successful create stores the website ID in `channel.reference`, which unlocks tags, pages, menus, files, and publishing.'."\n\n"
        .'For channel setup context, read `platform-manager://flycms/overview`. For tag/page editorial rules, read their dedicated guideline resources after the website exists.'."\n\n"
        .'Only the **mutation payload reference**, **response fields**, and **meta fields** sections are generated from FlyCMS contracts.';
    }

    public static function createMutationDataClass(): string
    {
        return CreateWebsiteData::class;
    }

    public static function updateMutationDataClass(): ?string
    {
        return UpdateWebsiteData::class;
    }

    public static function resourceClass(): ?string
    {
        return WebsiteResource::class;
    }

    public static function excludedMutationFields(): array
    {
        return [];
    }

    public static function excludedResourceFields(): array
    {
        return [
            'meta',
            'traffic_statistics',
            'domains_count',
            'public_posts_count',
            'created_at',
            'updated_at',
        ];
    }

    public static function sections(): array
    {
        $relatedTools = static::relatedTools();

        return [
            [
                'title' => 'What FlyCMS websites are',
                'content' => sprintf(
                    <<<'MARKDOWN'
A **website** is the root CMS container on a FlyCMS platform. Each FlyCMS channel provisions exactly one website; its ID becomes `channel.reference`.

```
Channel (client + flycms platform)
 └── reference → FlyCMS Website
      ├── Routes (assets, pages, posts, tags)
      ├── theme_id
      ├── meta (site-wide SEO defaults, pagination, theme config)
      ├── Domains, Pages, Tags, Menus, Files, Posts
      └── Themes (selected at setup)
```

**Internal vs public naming.** `name` is for internal/editorial display in MCP responses. Public site branding usually lives in meta `site-name`.

**Provisioning behavior.** %s is idempotent: if the channel already has a website reference, it returns the existing website instead of creating a duplicate.
MARKDOWN,
                    McpToolName::quoted($relatedTools['create']),
                ),
            ],
            [
                'title' => 'Route patterns',
                'content' => <<<'MARKDOWN'
Route fields define URL templates for the public site. Use leading slashes and FlyCMS placeholders in curly braces.

| Field | Placeholder | Example |
|-------|-------------|---------|
| `asset_route` | `{path}` | `/assets/{path}` |
| `page_route` | `{page}` | `/page/{page}` |
| `post_route` | `{post}` | `/post/{post}` |
| `website_tag_route` | `{websiteTag}` | `/tag/{websiteTag}` |

Guidelines:

1. **Set routes during provisioning** — pick patterns before creating pages, tags, or publishing posts.
2. **Keep placeholders exact** — `{page}`, `{post}`, and `{websiteTag}` are required token names.
3. **Stay consistent** — changing routes after content exists can break published URLs.
4. **Use simple paths** — lowercase segments work best, e.g. `/articles/{post}` instead of deeply nested paths.

Example route set:

```json
{
  "asset_route": "/assets/{path}",
  "page_route": "/page/{page}",
  "post_route": "/post/{post}",
  "website_tag_route": "/tag/{websiteTag}"
}
```
MARKDOWN,
            ],
            [
                'title' => 'Theme selection',
                'content' => sprintf(
                    <<<'MARKDOWN'
Assign `theme_id` during create or update to control layout, supported menu keys, and theme-specific behavior.

Workflow:

1. Use %s to browse available themes.
2. Use %s to inspect a theme's `guidelines` field for supported menu keys and editorial notes.
3. Pass the theme `id` as `theme_id` in `create_website_data` or `update_website_data`.

Most themes support `main` and `footer` menu keys by default. Always read the theme guidelines before creating menus.
MARKDOWN,
                    McpToolName::quoted($relatedTools['list_themes']),
                    McpToolName::quoted($relatedTools['show_theme']),
                ),
            ],
            [
                'title' => 'Liquid meta defaults',
                'content' => <<<'MARKDOWN'
Website `meta` stores site-wide defaults. Several SEO keys use **Liquid template syntax** and act as fallbacks when entity-level SEO fields are null:

| Meta key | Used as fallback for |
|----------|----------------------|
| `home-seo-title` / `home-seo-description` | Home page |
| `tag-seo-title` / `tag-seo-description` | Tag archive pages |
| `page-seo-title` / `page-seo-description` | Custom pages |
| `post-seo-title` / `post-seo-description` | Posts |

Other useful meta keys:

- `site-name` — public site label
- `items-per-page`, `query-page-name`, `query-limit-name` — pagination defaults
- `theme-config` — JSON-encoded theme configuration

Example SEO defaults:

```
{{ site.name }} | Sample Blog
Read the latest posts on {{ site.name }}.
```

Set defaults here first, then override per tag/page/post only when needed.
MARKDOWN,
            ],
            [
                'title' => 'Complete examples',
                'content' => <<<'MARKDOWN'
### Minimal website

```json
{
  "status": "active",
  "name": "Sample Blog"
}
```

### Website with routes and theme

```json
{
  "status": "active",
  "name": "Sample Blog",
  "asset_route": "/assets/{path}",
  "page_route": "/page/{page}",
  "post_route": "/post/{post}",
  "website_tag_route": "/tag/{websiteTag}",
  "theme_id": "01J00000000000000000000081"
}
```

### Update routes after provisioning

```json
{
  "post_route": "/articles/{post}",
  "website_tag_route": "/topics/{websiteTag}"
}
```
MARKDOWN,
            ],
            [
                'title' => 'Practical tips',
                'content' => sprintf(
                    "1. **Provision before CMS work** — create the website before tags, pages, menus, or publishing.\n"
                    ."2. **Verify provisioning** — use %s to confirm `channel.reference` and inspect routes/meta.\n"
                    ."3. **Pick a theme early** — use %s and %s before creating menus.\n"
                    ."4. **Set SEO defaults in meta** — configure `tag-seo-*`, `page-seo-*`, and `post-seo-*` before creating entities.\n"
                    .'5. **Keep `status` accurate** — use `inactive` only when the site should not serve public content.',
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['list_themes']),
                    McpToolName::quoted($relatedTools['show_theme']),
                ),
            ],
        ];
    }
}
