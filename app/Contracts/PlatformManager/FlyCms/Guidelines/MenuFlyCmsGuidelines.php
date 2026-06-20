<?php

namespace App\Contracts\PlatformManager\FlyCms\Guidelines;

use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\ProvidesFlyCmsGuidelines;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\CreateMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\DeleteMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\ListMenusTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\ShowMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\UpdateMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools\ShowThemeTool;

class MenuFlyCmsGuidelines implements ProvidesFlyCmsGuidelines
{
    public static function title(): string
    {
        return 'FlyCMS Menu Guidelines';
    }

    public static function relatedTools(): array
    {
        return [
            'create' => CreateMenuTool::class,
            'update' => UpdateMenuTool::class,
            'show' => ShowMenuTool::class,
            'list' => ListMenusTool::class,
            'delete' => DeleteMenuTool::class,
            'show_theme' => ShowThemeTool::class,
        ];
    }

    public static function intro(): string
    {
        $relatedTools = static::relatedTools();

        return sprintf(
            'Read this resource before creating or updating FlyCMS menus with %s.',
            McpToolName::quotedFromMap($relatedTools, 'create', 'update'),
        )."\n\n"
        .'Menus define website navigation — for example header (`main`) and footer (`footer`) link groups. Provision the website first — see `file://resources/website-guidelines-resource`.'."\n\n"
        .'Create pages and tags before linking to them — see `file://resources/page-guidelines-resource` and `file://resources/tag-guidelines-resource`.'."\n\n"
        .'`website_id` is set automatically from the channel reference; do not rely on passing a different website ID in MCP tools.'."\n\n"
        .'Only the **mutation payload reference** and **response fields** sections are generated from FlyCMS contracts. Item shape and link formats are documented below.';
    }

    public static function createMutationDataClass(): string
    {
        return CreateMenuData::class;
    }

    public static function updateMutationDataClass(): ?string
    {
        return UpdateMenuData::class;
    }

    public static function resourceClass(): ?string
    {
        return MenuResource::class;
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
                'title' => 'What FlyCMS menus are',
                'content' => sprintf(
                    <<<'MARKDOWN'
A **menu** is a named navigation group on a FlyCMS website. Themes render menus by `key` — for example a header bar from `main` and a footer row from `footer`.

```
Website
 └── Menus
      ├── key (theme slot, e.g. main, footer)
      └── items[]
           ├── text (label)
           ├── link (destination)
           ├── new_tab (0 or 1)
           └── items[] (optional nested submenu)
```

**One menu per key.** Each `key` identifies a navigation slot. Use %s to inspect existing menus before creating duplicates for the same key.
MARKDOWN,
                    McpToolName::quoted($relatedTools['list']),
                ),
            ],
            [
                'title' => 'Menu keys',
                'content' => sprintf(
                    <<<'MARKDOWN'
`key` is a kebab-case identifier that tells the theme which navigation area to fill.

### Common keys

| Key | Typical use |
|-----|-------------|
| `main` | Primary header navigation |
| `footer` | Footer links |

Most themes support at least `main` and `footer`. Other themes may define additional keys.

### Theme-specific keys

Inspect the website theme before choosing keys:

1. Use %s to read the theme `guidelines` field.
2. Pass only keys the active theme documents.

Use a stable key per navigation area — changing keys after launch may leave the theme without expected menu content.
MARKDOWN,
                    McpToolName::quoted($relatedTools['show_theme']),
                ),
            ],
            [
                'title' => 'Menu item structure',
                'content' => <<<'MARKDOWN'
Each entry in `items` is an object with:

| Field | Required | Description |
|-------|----------|-------------|
| `text` | Yes | Label shown in the navigation |
| `link` | Yes | Destination — see link formats below |
| `new_tab` | No | `0` opens in the current tab (default), `1` opens in a new tab |
| `items` | No | Nested submenu items (up to three levels deep) |

Guidelines:

1. **Keep labels short** — one to three words for top-level items.
2. **Order matters** — items render in array order.
3. **Use nesting sparingly** — one submenu level is usually enough.
4. **Replace the full tree on update** — passing `items` replaces the entire menu tree for that menu.
MARKDOWN,
            ],
            [
                'title' => 'Link formats',
                'content' => <<<'MARKDOWN'
`link` supports three formats:

### 1. Relative path

Use for on-site pages, posts, tags, or the homepage. Build paths from the website route settings in `file://resources/website-guidelines-resource`.

| Target | Example `page_route` | Example link |
|--------|----------------------|--------------|
| Homepage | — | `/` |
| Page slug `about` | `/page/{page}` | `/page/about` |
| Post slug `hello-world` | `/post/{post}` | `/post/hello-world` |
| Tag slug `technology` | `/tag/{websiteTag}` | `/tag/technology` |

Always match the provisioned route pattern — do not assume `/about` when `page_route` is `/page/{page}`.

### 2. Full URL

Use for external destinations:

```
https://example.com/docs
```

Set `new_tab` to `1` for external links when the theme should open them separately.

### 3. Tag reference

Use when linking directly to a tag by FlyCMS ID:

```
link:website_tag,01hw720nn5ef2dztvftfg5m47q
```

Resolve tag IDs with the tag show/list tools before building menu payloads.
MARKDOWN,
            ],
            [
                'title' => 'Complete examples',
                'content' => <<<'MARKDOWN'
### Main header menu

```json
{
  "key": "main",
  "items": [
    {
      "text": "Home",
      "link": "/",
      "new_tab": 0
    },
    {
      "text": "About",
      "link": "/page/about",
      "new_tab": 0
    },
    {
      "text": "Blog",
      "link": "/post/hello-world",
      "new_tab": 0
    }
  ]
}
```

### Footer menu

```json
{
  "key": "footer",
  "items": [
    {
      "text": "Privacy",
      "link": "/page/privacy",
      "new_tab": 0
    },
    {
      "text": "Contact",
      "link": "/page/contact",
      "new_tab": 0
    }
  ]
}
```

### Nested submenu with tag link

```json
{
  "key": "main",
  "items": [
    {
      "text": "Topics",
      "link": "/tag/technology",
      "new_tab": 0,
      "items": [
        {
          "text": "Shop",
          "link": "link:website_tag,01J00000000000000000000071",
          "new_tab": 0
        }
      ]
    }
  ]
}
```
MARKDOWN,
            ],
            [
                'title' => 'Practical tips',
                'content' => sprintf(
                    "1. **Confirm routes first** — read website `page_route`, `post_route`, and `website_tag_route` before writing relative links.\n"
                    ."2. **List existing menus** — use %s to avoid duplicate keys.\n"
                    ."3. **Inspect theme keys** — use %s when a theme may not support `main` / `footer`.\n"
                    ."4. **Verify after changes** — use %s to confirm item order, links, and nesting.\n"
                    .'5. **Delete carefully** — use %s only when a menu should be permanently removed.',
                    McpToolName::quoted($relatedTools['list']),
                    McpToolName::quoted($relatedTools['show_theme']),
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['delete']),
                ),
            ],
        ];
    }
}
