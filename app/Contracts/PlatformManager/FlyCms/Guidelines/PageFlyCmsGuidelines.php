<?php

namespace App\Contracts\PlatformManager\FlyCms\Guidelines;

use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\CreatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\UpdatePageData;
use App\Contracts\PlatformManager\FlyCms\ProvidesFlyCmsGuidelines;
use App\Contracts\PlatformManager\FlyCms\Resources\PageResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\CreatePageTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\DeletePageTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\ListPagesTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\ShowPageTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\UpdatePageTool;

class PageFlyCmsGuidelines implements ProvidesFlyCmsGuidelines
{
    public static function title(): string
    {
        return 'FlyCMS Page Guidelines';
    }

    public static function relatedTools(): array
    {
        return [
            'create' => CreatePageTool::class,
            'update' => UpdatePageTool::class,
            'show' => ShowPageTool::class,
            'list' => ListPagesTool::class,
            'delete' => DeletePageTool::class,
        ];
    }

    public static function intro(): string
    {
        $relatedTools = static::relatedTools();

        return sprintf(
            'Read this resource before creating or updating FlyCMS pages with %s.',
            McpToolName::quotedFromMap($relatedTools, 'create', 'update'),
        )."\n\n"
        .'Pages are static CMS content served at the website `page_route` (for example `/page/{page}`). Provision the website first — see `file://resources/website-guidelines-resource`.'."\n\n"
        .'`website_id` is set automatically from the channel reference; do not rely on passing a different website ID in MCP tools.'."\n\n"
        .'Only the **mutation payload reference** and **response fields** sections are generated from FlyCMS contracts.';
    }

    public static function createMutationDataClass(): string
    {
        return CreatePageData::class;
    }

    public static function updateMutationDataClass(): ?string
    {
        return UpdatePageData::class;
    }

    public static function resourceClass(): ?string
    {
        return PageResource::class;
    }

    public static function excludedMutationFields(): array
    {
        return ['website_id'];
    }

    public static function excludedResourceFields(): array
    {
        return [
            'created_at',
            'updated_at',
        ];
    }

    public static function sections(): array
    {
        $relatedTools = static::relatedTools();

        return [
            [
                'title' => 'What FlyCMS pages are',
                'content' => <<<'MARKDOWN'
A **page** is a custom static content page on a FlyCMS website — for example About, Contact, or Shipping Policy.

```
Website
 └── Pages
      ├── slug (URL segment via page_route)
      ├── title (editorial heading)
      ├── seo_title / seo_description / content (all parsed by Liquid)
```

**Pages vs posts vs tags.** Pages are standalone editorial content with a fixed slug. Posts are article-driven publishing output. Tags are taxonomy archive landing pages.

**Menus.** Pages are often linked from menus using a relative path (for example `/page/about`) or a full URL.
MARKDOWN,
            ],
            [
                'title' => 'Identity rules',
                'content' => <<<'MARKDOWN'
1. **Pick a clear `slug`** — kebab-case URL segment, e.g. `about-us`, `privacy-policy`.
2. **Use `title` for the editorial page name** — shown in CMS output and often used in SEO templates.
3. **Keep slugs stable** — changing a slug after publishing breaks existing links and menu entries.
4. **Match the website route** — the public URL is built from `page_route` on the website plus the page slug.
MARKDOWN,
            ],
            [
                'title' => 'Liquid template fields',
                'content' => <<<'MARKDOWN'
`seo_title`, `seo_description`, and `content` are **parsed by the Liquid engine** before output. FlyCMS renders them in the context of the current page.

### Syntax

- Output a value: `{{ page.field }}`
- Literal text mixed with variables: `{{ page.title }} | Sample Blog`
- Leave `seo_title` or `seo_description` `null` to fall back to website meta defaults:
  - `page-seo-title`
  - `page-seo-description`

### Available `page` variables

| Variable | Description |
|----------|-------------|
| `{{ page.title }}` | Page title |
| `{{ page.slug }}` | Page URL slug |

Prefer `{{ page.title }}` in SEO fields unless you need another value.

SEO examples:

```
{{ page.title }} | Sample Blog
Learn more about {{ page.title }} on Sample Blog.
```
MARKDOWN,
            ],
            [
                'title' => '`content` field (Liquid)',
                'content' => <<<'MARKDOWN'
`content` is a **Liquid template** parsed by the same engine as the SEO fields. Use it for the page body.

Guidelines:

1. Use `{{ page.* }}` for copy that should follow the page record.
2. Plain HTML without Liquid tags still works — the engine treats it as static markup.
3. Treat `{{` and `{%` as Liquid syntax; they are interpreted, not passed through literally.
4. Omit `content` when the theme supplies the page body.

Examples:

```
<h1>{{ page.title }}</h1>
<p>Welcome to our about page.</p>
```

```
<p>Learn more about {{ page.title }}.</p>
```
MARKDOWN,
            ],
            [
                'title' => 'Complete examples',
                'content' => <<<'MARKDOWN'
### Minimal page

```json
{
  "slug": "about",
  "title": "About Us"
}
```

### Page with SEO and content

```json
{
  "slug": "about",
  "title": "About Us",
  "seo_title": "About Us | Sample Blog",
  "seo_description": "Learn more about Sample Blog.",
  "content": "<h1>{{ page.title }}</h1>\n<p>Welcome to our about page.</p>"
}
```

### Static copy (no Liquid variables)

```json
{
  "slug": "shipping",
  "title": "Shipping Policy",
  "seo_title": "Shipping | Demo Storefront",
  "seo_description": "Shipping information for Demo Storefront.",
  "content": "<p>Shipping details.</p>"
}
```

Plain HTML and text in `content` still pass through the Liquid engine; they simply have no `{{` or `{%` tags to expand.
MARKDOWN,
            ],
            [
                'title' => 'Practical tips',
                'content' => sprintf(
                    "1. **Provision the website first** — confirm `page_route` via %s before creating pages.\n"
                    ."2. **Reuse website SEO defaults** — leave `seo_title` / `seo_description` null unless a page needs custom metadata.\n"
                    ."3. **Use Liquid in `content`** — `{{ page.title }}` and other variables resolve when the body should track the page record.\n"
                    ."4. **List before creating duplicates** — use %s to check for an existing slug.\n"
                    ."5. **Inspect results** — use %s after create/update to verify fields.\n"
                    .'6. **Delete carefully** — use %s only when a page should be permanently removed.',
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['list']),
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['delete']),
                ),
            ],
        ];
    }
}
