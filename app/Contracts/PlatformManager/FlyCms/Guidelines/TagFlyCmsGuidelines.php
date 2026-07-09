<?php

namespace App\Contracts\PlatformManager\FlyCms\Guidelines;

use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\ProvidesFlyCmsGuidelines;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\ArticleTools\PublishArticleTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\CreateFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\ListFilesTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\CreateTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\ShowTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\UpdateTagTool;

class TagFlyCmsGuidelines implements ProvidesFlyCmsGuidelines
{
    public static function title(): string
    {
        return 'FlyCMS Tag Guidelines';
    }

    public static function relatedTools(): array
    {
        return [
            'create' => CreateTagTool::class,
            'update' => UpdateTagTool::class,
            'show' => ShowTagTool::class,
            'list_files' => ListFilesTool::class,
            'create_file' => CreateFileTool::class,
            'publish_article' => PublishArticleTool::class,
        ];
    }

    public static function intro(): string
    {
        $relatedTools = static::relatedTools();

        return sprintf(
            'Use with %s when creating or updating FlyCMS tags.',
            McpToolName::quotedFromMap($relatedTools, 'create', 'update'),
        )."\n\n"
        ."For channel setup, see `platform-manager://flycms/overview`.\n\n"
        .'`website_id` comes from `channel.reference`; do not pass a different website ID.';
    }

    public static function createMutationDataClass(): string
    {
        return CreateTagData::class;
    }

    public static function updateMutationDataClass(): ?string
    {
        return UpdateTagData::class;
    }

    public static function resourceClass(): ?string
    {
        return TagResource::class;
    }

    public static function excludedMutationFields(): array
    {
        return ['website_id'];
    }

    public static function excludedResourceFields(): array
    {
        return ['thumbnailFile'];
    }

    public static function sections(): array
    {
        $relatedTools = static::relatedTools();

        return [
            [
                'title' => 'Identity rules',
                'content' => <<<'MARKDOWN'
1. **`name`** — canonical identity; fixed after creation.
2. **`display_name`** — public label without creating a new tag.
3. **`slug`** — kebab-case, e.g. `weekend-travel`, `ai-tools`.
4. **`description`** — one plain-text sentence; not HTML or Liquid.
MARKDOWN,
            ],
            [
                'title' => 'Liquid template fields',
                'content' => <<<'MARKDOWN'
`seo_title`, `seo_description`, `seo_h1`, and `content` are **parsed by the Liquid engine** in tag context.

### Syntax

- Output: `{{ tag.field }}`
- Mixed text: `{{ tag.name }} | Sample Blog`
- `null` SEO falls back to website meta `tag-seo-title` / `tag-seo-description`

### Available `tag` variables

| Variable | Description |
|----------|-------------|
| `{{ tag.name }}` | Tag display name |
| `{{ tag.display_name }}` | Same as `tag.name` when set |
| `{{ tag.slug }}` | URL slug |
| `{{ tag.description }}` | Short description |

Prefer `{{ tag.name }}` for titles and headings.

### `seo_title` / `seo_description`

Search/browser copy. Keep titles short; descriptions summarize the tag archive.

### `seo_h1`

Primary on-page heading. Plain text or HTML when the theme expects rich markup.

Examples:

```
{{ tag.name }} | Sample Blog
<h1>{{ tag.name }}</h1>
```
MARKDOWN,
            ],
            [
                'title' => '`content` field (Liquid)',
                'content' => <<<'MARKDOWN'
`content` is a **Liquid template** for a short intro above the post list.

1. Use `{{ tag.* }}` when copy should track the tag record.
2. Plain HTML without Liquid tags works as static markup.
3. `{{` and `{%` are interpreted as Liquid syntax.
4. Keep brief — a lead paragraph is enough; posts carry long-form content.
5. Omit when no intro copy is needed.

Examples:

```
<p>Browse our latest {{ tag.name }} articles, tutorials, and product reviews.</p>
```

```
<p>Technology tag landing page.</p>
```
MARKDOWN,
            ],
            [
                'title' => 'Complete examples',
                'content' => <<<'MARKDOWN'
### Minimal tag

```json
{
  "name": "Lifestyle",
  "slug": "lifestyle"
}
```

### Tag with SEO and intro content

```json
{
  "name": "Technology",
  "slug": "technology",
  "description": "Articles about technology and software.",
  "is_featured": true,
  "thumbnail_file_id": "01J00000000000000000000071",
  "seo_title": "{{ tag.name }} | Sample Blog",
  "seo_description": "Read the latest technology posts on Sample Blog.",
  "seo_h1": "{{ tag.name }}",
  "content": "<p>Browse our latest {{ tag.name }} posts on Sample Blog.</p>"
}
```

### Tag with static SEO (no Liquid)

```json
{
  "name": "Shop",
  "slug": "shop",
  "description": "Storefront product topics.",
  "seo_title": "Shop | Demo Storefront",
  "seo_description": "Browse products by topic.",
  "seo_h1": "Shop"
}
```
MARKDOWN,
            ],
            [
                'title' => 'Practical tips',
                'content' => sprintf(
                    "1. **Tags before publishing** — %s publishes posts to the website.\n"
                    ."2. **Reuse SEO defaults** — leave `seo_title` / `seo_description` null when possible.\n"
                    ."3. **`is_featured` sparingly** — reserve for homepage/navigation highlights.\n"
                    .'4. **Thumbnails first** — see `platform-manager://flycms/file-guidelines`, then %s or %s; pass `thumbnail_file_id`.'."\n"
                    .'5. **Verify** — %s after create/update.',
                    McpToolName::quoted($relatedTools['publish_article']),
                    McpToolName::quoted($relatedTools['create_file']),
                    McpToolName::quoted($relatedTools['list_files']),
                    McpToolName::quoted($relatedTools['show']),
                ),
            ],
        ];
    }
}
