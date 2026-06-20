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
            'Read this resource before creating or updating FlyCMS tags with %s.',
            McpToolName::quotedFromMap($relatedTools, 'create', 'update'),
        )."\n\n"
        ."For channel setup and website provisioning, start with `platform-manager://flycms/overview`.\n\n"
        .'`website_id` is set automatically from the channel reference; do not rely on passing a different website ID in MCP tools.';
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
1. **Pick `name` carefully** — it is the canonical tag identity and is fixed after creation.
2. **Use `display_name` for public wording** — update this when the label shown on the site should change without creating a new tag.
3. **Keep `slug` in kebab-case** — lowercase words separated by hyphens, e.g. `weekend-travel`, `ai-tools`.
4. **Keep `description` concise** — one sentence is usually enough; it is plain text, not HTML or Liquid.
MARKDOWN,
            ],
            [
                'title' => 'Liquid template fields',
                'content' => <<<'MARKDOWN'
`seo_title`, `seo_description`, `seo_h1`, and `content` are **parsed by the Liquid engine** before output. FlyCMS renders them in the context of the current tag page.

### Syntax

- Output a value: `{{ tag.field }}`
- Literal text mixed with variables: `{{ tag.name }} | Sample Blog`
- Leave a field `null` to fall back to website-level defaults in website meta:
  - `tag-seo-title`
  - `tag-seo-description`

Set explicit per-tag values when a tag needs custom SEO or heading copy.

### Available `tag` variables

| Variable | Description |
|----------|-------------|
| `{{ tag.name }}` | Tag display name shown on the site |
| `{{ tag.display_name }}` | Same public label as `tag.name` when set |
| `{{ tag.slug }}` | Tag URL slug |
| `{{ tag.description }}` | Short tag description, if set |

Prefer `{{ tag.name }}` for titles and headings unless you need another field.

### `seo_title` and `seo_description`

- Write for search results and browser tabs.
- Keep titles short and specific to the tag topic.
- Descriptions should summarize what readers will find in that tag archive.

Examples:

```
{{ tag.name }} | Sample Blog
Read the latest {{ tag.name }} posts on Sample Blog.
```

### `seo_h1`

- Controls the primary on-page heading.
- Liquid output is injected into the page heading area; plain text is usually enough.
- You may include HTML when the theme expects rich heading markup.

Examples:

```
{{ tag.name }}
<h1>{{ tag.name }}</h1>
```
MARKDOWN,
            ],
            [
                'title' => '`content` field (Liquid)',
                'content' => <<<'MARKDOWN'
`content` is a **Liquid template** parsed by the same engine as the SEO fields. Use it for a short introduction above the tag's post list.

Guidelines:

1. Use `{{ tag.* }}` when intro copy should follow the tag record.
2. Plain HTML without Liquid tags still works — the engine treats it as static markup.
3. Treat `{{` and `{%` as Liquid syntax; they are interpreted, not passed through literally.
4. Keep it brief — a lead paragraph or short overview is enough.
5. Do not paste full articles here; posts carry long-form content.
6. Omit `content` when the tag archive does not need intro copy.

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
                    "1. **Create tags before publishing articles** — use %s to publish posts to the website.\n"
                    ."2. **Reuse website SEO defaults** — leave `seo_title` / `seo_description` null unless a tag needs custom metadata.\n"
                    ."3. **Use `is_featured` sparingly** — reserve featured tags for homepage or navigation highlights.\n"
                    .'4. **Upload thumbnails first** — use %s or %s, then pass `thumbnail_file_id` when creating the tag.'."\n"
                    .'5. **Inspect results** — use %s after create/update to verify rendered fields.',
                    McpToolName::quoted($relatedTools['publish_article']),
                    McpToolName::quoted($relatedTools['create_file']),
                    McpToolName::quoted($relatedTools['list_files']),
                    McpToolName::quoted($relatedTools['show']),
                ),
            ],
        ];
    }
}
