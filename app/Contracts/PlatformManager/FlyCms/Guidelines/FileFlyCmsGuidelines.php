<?php

namespace App\Contracts\PlatformManager\FlyCms\Guidelines;

use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\UpdateFileData;
use App\Contracts\PlatformManager\FlyCms\ProvidesFlyCmsGuidelines;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\CreateFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\DeleteFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\ListFilesTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\ShowFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\UpdateFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\CreateTagTool;

class FileFlyCmsGuidelines implements ProvidesFlyCmsGuidelines
{
    public static function title(): string
    {
        return 'FlyCMS File Guidelines';
    }

    public static function relatedTools(): array
    {
        return [
            'create' => CreateFileTool::class,
            'update' => UpdateFileTool::class,
            'show' => ShowFileTool::class,
            'list' => ListFilesTool::class,
            'delete' => DeleteFileTool::class,
            'create_tag' => CreateTagTool::class,
        ];
    }

    public static function intro(): string
    {
        $relatedTools = static::relatedTools();

        return sprintf(
            'Read this resource before uploading or managing FlyCMS files with %s.',
            McpToolName::quotedFromMap($relatedTools, 'create', 'update', 'list'),
        )."\n\n"
        .'Files are media assets stored on the FlyCMS platform — images and videos used as tag thumbnails, post media, and other CMS attachments.'."\n\n"
        .'File operations require a FlyCMS `channel_id` but **not** a provisioned website. Uploads are scoped to the authenticated MCP user\'s FlyCMS account on that platform.'."\n\n"
        .'Only the **mutation payload reference** and **response fields** sections are generated from FlyCMS contracts. The base64 upload field is documented below.';
    }

    public static function createMutationDataClass(): string
    {
        return CreateFileData::class;
    }

    public static function updateMutationDataClass(): ?string
    {
        return UpdateFileData::class;
    }

    public static function resourceClass(): ?string
    {
        return FileResource::class;
    }

    public static function excludedMutationFields(): array
    {
        return [];
    }

    public static function excludedResourceFields(): array
    {
        return [];
    }

    public static function sections(): array
    {
        $relatedTools = static::relatedTools();

        return [
            [
                'title' => 'What FlyCMS files are',
                'content' => <<<'MARKDOWN'
A **file** is an uploaded media record on FlyCMS — typically an image or video binary stored in platform storage.

```
Channel (flycms platform)
 └── Files (per authenticated FlyCMS user)
      ├── id (use in thumbnail_file_id and other references)
      ├── code (optional stable lookup key)
      ├── type / mime / size
      └── url (public URL when uploaded)
```

**Common uses.** Tag thumbnails (`thumbnail_file_id`), post media, and reusable branded assets referenced by FlyCMS ID.

**User scope.** List and access rules filter files to the MCP user's FlyCMS user account. You cannot manage another user's uploads through these tools.
MARKDOWN,
            ],
            [
                'title' => 'Upload workflow',
                'content' => sprintf(
                    <<<'MARKDOWN'
Uploads use %s with three top-level fields:

| Field | Description |
|-------|-------------|
| `channel_id` | FlyCMS channel ULID |
| `file_data` | **Base64-encoded** file binary |
| `create_file_data` | Metadata object — at minimum `ext` |

### Steps

1. Read the source file bytes from disk or generated output.
2. Base64-encode the binary content for `file_data`.
3. Pass `create_file_data.ext` matching the real file format.
4. Optionally set `filename`, `code`, or `information`.
5. Store the returned `id` for later references — for example `thumbnail_file_id` in %s.

`ext` and `filename` cannot be changed after upload. To replace a file, upload a new one and update downstream references.
MARKDOWN,
                    McpToolName::quoted($relatedTools['create']),
                    McpToolName::quoted($relatedTools['create_tag']),
                ),
            ],
            [
                'title' => 'Supported extensions',
                'content' => <<<'MARKDOWN'
`ext` must be one of the supported values below. FlyCMS derives `type` and `mime` from the extension.

| `ext` | `type` | Typical use |
|-------|--------|-------------|
| `jpg`, `jpeg` | `image` | Photos, thumbnails |
| `png` | `image` | Graphics with transparency |
| `webp` | `image` | Optimized web images |
| `gif` | `image` | Animated or simple graphics |
| `mp4` | `video` | Short video clips |
| `webm` | `video` | Web-optimized video |

Pick the extension that matches the encoded bytes in `file_data`. Mismatched extensions can produce incorrect mime types or upload failures.
MARKDOWN,
            ],
            [
                'title' => 'Metadata fields',
                'content' => <<<'MARKDOWN'
### `filename`

Optional original filename hint. When omitted, FlyCMS generates a storage name from the upload.

### `code`

Optional stable lookup key for your own workflows. Use kebab-case or snake_case identifiers such as `hero-banner` or `tag_technology_thumb`.

List files by `code` through `file_filter.code` on the list tool.

### `information`

Optional JSON object for extra metadata — for example image `alt` text, `width`, `height`, or video `duration`. FlyCMS stores this object as-is; themes and templates decide how to use it.

Updates can change `code` and `information` only. Binary content is not replaced through the update tool.
MARKDOWN,
            ],
            [
                'title' => 'Complete examples',
                'content' => <<<'MARKDOWN'
### Upload a PNG thumbnail

```json
{
  "channel_id": "01J00000000000000000000001",
  "file_data": "<base64-encoded-png-bytes>",
  "create_file_data": {
    "ext": "png",
    "filename": "technology-thumb",
    "code": "tag-technology-thumb",
    "information": {
      "alt": "Technology tag thumbnail"
    }
  }
}
```

### Upload a WebP image with minimal metadata

```json
{
  "channel_id": "01J00000000000000000000001",
  "file_data": "<base64-encoded-webp-bytes>",
  "create_file_data": {
    "ext": "webp"
  }
}
```

### Update file metadata only

```json
{
  "channel_id": "01J00000000000000000000001",
  "file_id": "01J00000000000000000000071",
  "update_file_data": {
    "code": "hero-banner",
    "information": {
      "alt": "Homepage hero image",
      "width": 1200,
      "height": 630
    }
  }
}
```
MARKDOWN,
            ],
            [
                'title' => 'Practical tips',
                'content' => sprintf(
                    "1. **Upload before referencing** — create the file first, then pass its `id` to tag/post mutations.\n"
                    ."2. **List to reuse assets** — use %s to find an existing thumbnail instead of uploading duplicates.\n"
                    ."3. **Keep base64 valid** — invalid `file_data` encoding fails before upload starts.\n"
                    ."4. **Inspect uploads** — use %s to confirm `url`, `type`, `size`, and `is_uploaded`.\n"
                    .'5. **Delete carefully** — use %s only when nothing still references the file ID.',
                    McpToolName::quoted($relatedTools['list']),
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['delete']),
                ),
            ],
        ];
    }
}
