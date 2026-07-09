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
            'Use with %s when creating or updating FlyCMS pages.',
            McpToolName::quotedFromMap($relatedTools, 'create', 'update'),
        )."\n\n"
        .'Static CMS pages served at `page_route` (e.g. `/page/{page}`). Provision the website first — see `platform-manager://flycms/website-guidelines`.'."\n\n"
        .'`website_id` comes from `channel.reference`; do not pass a different website ID.'."\n\n"
        .'Schema tables below are generated from FlyCMS contracts.';
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
Custom static pages — About, Contact, policies, etc.

```
Website
 └── Pages
      ├── slug (URL segment via page_route)
      ├── title (editorial heading)
      ├── seo_title / seo_description / content (all parsed by Liquid)
```

**Pages vs posts vs tags.** Pages are fixed-slug editorial content; posts are published articles; tags are taxonomy archives.

**Menus.** Link pages with relative paths (e.g. `/page/about`) or full URLs.
MARKDOWN,
            ],
            [
                'title' => 'Identity rules',
                'content' => <<<'MARKDOWN'
1. **`slug`** — kebab-case URL segment, e.g. `about-us`, `privacy-policy`.
2. **`title`** — editorial name; often used in SEO templates.
3. **Stable slugs** — changes break links and menu entries.
4. **Match `page_route`** — public URL = website route + slug.
MARKDOWN,
            ],
            [
                'title' => 'Liquid template fields',
                'content' => <<<'MARKDOWN'
`seo_title`, `seo_description`, and `content` are **parsed by the Liquid engine** in page context.

### Syntax

- Output: `{{ page.field }}`
- Mixed text: `{{ page.title }} | Sample Blog`
- `null` SEO falls back to website meta `page-seo-title` / `page-seo-description`

### Available `page` variables

| Variable | Description |
|----------|-------------|
| `{{ page.title }}` | Page title |
| `{{ page.slug }}` | Page URL slug |

Prefer `{{ page.title }}` unless another field is needed.

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
`content` is a **Liquid template** for the page body (same engine as SEO fields).

1. Use `{{ page.* }}` when copy should track the page record.
2. Plain HTML without Liquid tags works as static markup.
3. `{{` and `{%` are interpreted as Liquid syntax.
4. Omit `content` when the theme supplies the body.

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

Plain HTML/text without Liquid tags still passes through the engine unchanged.
MARKDOWN,
            ],
            [
                'title' => 'Practical tips',
                'content' => sprintf(
                    "1. **Website first** — confirm `page_route` via %s.\n"
                    ."2. **Reuse SEO defaults** — leave `seo_title` / `seo_description` null when possible.\n"
                    ."3. **Liquid in `content`** — `{{ page.title }}` tracks the page record.\n"
                    ."4. **List before create** — %s avoids duplicate slugs.\n"
                    ."5. **Verify** — %s after create/update.\n"
                    .'6. **Delete carefully** — %s removes the page permanently.',
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['list']),
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['delete']),
                ),
            ],
        ];
    }
}
