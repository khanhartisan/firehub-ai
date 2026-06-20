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
            'Use with %s when creating or updating FlyCMS menus.',
            McpToolName::quotedFromMap($relatedTools, 'create', 'update'),
        )."\n\n"
        .'Navigation groups — e.g. header `main`, footer `footer`. Provision the website first — see `file://resources/website-guidelines-resource`.'."\n\n"
        .'Create pages and tags before linking them — see page and tag guideline resources.'."\n\n"
        .'`website_id` comes from `channel.reference`; do not pass a different website ID.'."\n\n"
        .'Schema tables below are generated from FlyCMS contracts. Item shape and link formats are documented below.';
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
Named navigation groups rendered by theme `key` — e.g. `main` (header), `footer`.

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

**One menu per key.** Use %s before creating duplicate keys.
MARKDOWN,
                    McpToolName::quoted($relatedTools['list']),
                ),
            ],
            [
                'title' => 'Menu keys',
                'content' => sprintf(
                    <<<'MARKDOWN'
Kebab-case theme slot identifier.

### Common keys

| Key | Typical use |
|-----|-------------|
| `main` | Primary header navigation |
| `footer` | Footer links |

Most themes support `main` and `footer`. Other themes may define more.

1. %s — read theme `guidelines`.
2. Use only keys the active theme documents.

Keep keys stable — changes can leave themes without expected menu content.
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

1. **Short labels** — one to three words for top-level items.
2. **Order matters** — array order is render order.
3. **Nest sparingly** — one submenu level is usually enough.
4. **Full tree on update** — `items` replaces the entire menu.
MARKDOWN,
            ],
            [
                'title' => 'Link formats',
                'content' => <<<'MARKDOWN'
`link` supports three formats:

### 1. Relative path

On-site paths from website routes in `file://resources/website-guidelines-resource`.

| Target | Example `page_route` | Example link |
|--------|----------------------|--------------|
| Homepage | — | `/` |
| Page slug `about` | `/page/{page}` | `/page/about` |
| Post slug `hello-world` | `/post/{post}` | `/post/hello-world` |
| Tag slug `technology` | `/tag/{websiteTag}` | `/tag/technology` |

Match provisioned route patterns — not `/about` when `page_route` is `/page/{page}`.

### 2. Full URL

External destinations, e.g. `https://example.com/docs`. Set `new_tab` to `1` when opening separately.

### 3. Tag reference

Direct tag by FlyCMS ID, e.g. `link:website_tag,01hw720nn5ef2dztvftfg5m47q`. Resolve IDs via tag show/list tools first.
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
                    "1. **Confirm routes** — read `page_route`, `post_route`, `website_tag_route` first.\n"
                    ."2. **List menus** — %s avoids duplicate keys.\n"
                    ."3. **Check theme keys** — %s when `main` / `footer` may be unsupported.\n"
                    ."4. **Verify** — %s confirms order, links, nesting.\n"
                    .'5. **Delete carefully** — %s removes the menu permanently.',
                    McpToolName::quoted($relatedTools['list']),
                    McpToolName::quoted($relatedTools['show_theme']),
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['delete']),
                ),
            ],
        ];
    }
}
