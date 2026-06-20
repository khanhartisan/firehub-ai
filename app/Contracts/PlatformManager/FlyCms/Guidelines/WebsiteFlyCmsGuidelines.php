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
            'Use with %s or %s when provisioning or updating a FlyCMS website.',
            McpToolName::quoted($relatedTools['create']),
            McpToolName::quoted($relatedTools['update']),
        )."\n\n"
        .'First CMS step on a channel — success stores the website ID in `channel.reference`, unlocking tags, pages, menus, files, and publishing.'."\n\n"
        .'See `platform-manager://flycms/overview` for channel setup. Schema tables below are generated from FlyCMS contracts.';
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
Each FlyCMS channel provisions one **website**; its ID becomes `channel.reference`.

```
Channel (client + flycms platform)
 └── reference → FlyCMS Website
      ├── Routes (assets, pages, posts, tags)
      ├── theme_id
      ├── meta (site-wide SEO defaults, pagination, theme config)
      ├── Domains, Pages, Tags, Menus, Files, Posts
      └── Themes (selected at setup)
```

**Naming.** `name` is internal/editorial; public branding usually lives in meta `site-name`.

**Idempotent create.** %s returns the existing website when `channel.reference` is already set.
MARKDOWN,
                    McpToolName::quoted($relatedTools['create']),
                ),
            ],
            [
                'title' => 'Route patterns',
                'content' => <<<'MARKDOWN'
URL templates for the public site. Use leading slashes and `{placeholder}` tokens.

| Field | Placeholder | Example |
|-------|-------------|---------|
| `asset_route` | `{path}` | `/assets/{path}` |
| `page_route` | `{page}` | `/page/{page}` |
| `post_route` | `{post}` | `/post/{post}` |
| `website_tag_route` | `{websiteTag}` | `/tag/{websiteTag}` |

Guidelines:

1. **Set routes at provisioning** — before pages, tags, or posts.
2. **Keep placeholders exact** — `{page}`, `{post}`, `{websiteTag}`.
3. **Stay consistent** — route changes can break published URLs.
4. **Prefer simple paths** — e.g. `/articles/{post}` over deep nesting.

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
Set `theme_id` on create/update for layout, menu keys, and theme behavior.

1. %s — browse themes.
2. %s — read `guidelines` for supported menu keys.
3. Pass theme `id` as `theme_id` in `create_website_data` or `update_website_data`.

Most themes support `main` and `footer`. Read theme guidelines before creating menus.
MARKDOWN,
                    McpToolName::quoted($relatedTools['list_themes']),
                    McpToolName::quoted($relatedTools['show_theme']),
                ),
            ],
            [
                'title' => 'Liquid meta defaults',
                'content' => <<<'MARKDOWN'
Site-wide defaults in `meta`. SEO keys use **Liquid** and fall back when entity SEO is null:

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

Set defaults here; override per entity only when needed.
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
                    "1. **Provision first** — website before tags, pages, menus, or publishing.\n"
                    ."2. **Verify** — %s confirms `channel.reference`, routes, and meta.\n"
                    ."3. **Theme early** — %s and %s before menus.\n"
                    ."4. **SEO defaults** — set `tag-seo-*`, `page-seo-*`, `post-seo-*` in meta first.\n"
                    .'5. **`status`** — use `inactive` only when the site should not be public.',
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['list_themes']),
                    McpToolName::quoted($relatedTools['show_theme']),
                ),
            ],
        ];
    }
}
