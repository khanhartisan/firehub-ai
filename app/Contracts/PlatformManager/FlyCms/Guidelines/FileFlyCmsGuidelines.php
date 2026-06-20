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
            'Use with %s when uploading or managing FlyCMS files.',
            McpToolName::quotedFromMap($relatedTools, 'create', 'update', 'list'),
        )."\n\n"
        .'Media assets — images and videos for tag thumbnails, post media, and CMS attachments.'."\n\n"
        .'Requires `channel_id` but **not** a provisioned website. Uploads are scoped to the authenticated user\'s FlyCMS account.'."\n\n"
        .'Schema tables below are generated from FlyCMS contracts. The base64 upload field is documented below.';
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
Uploaded media — images/videos in platform storage.

```
Channel (flycms platform)
 └── Files (per authenticated FlyCMS user)
      ├── id (use in thumbnail_file_id and other references)
      ├── code (optional stable lookup key)
      ├── type / mime / size
      └── url (public URL when uploaded)
```

**Uses.** Tag thumbnails (`thumbnail_file_id`), post media, reusable assets by FlyCMS ID.

**Scope.** Files belong to the MCP user's FlyCMS account; you cannot manage another user's uploads.
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

1. Read source bytes.
2. Base64-encode for `file_data`.
3. Set `create_file_data.ext` to match the format.
4. Optionally set `filename`, `code`, or `information`.
5. Store returned `id` — e.g. `thumbnail_file_id` in %s.

`ext` and `filename` are immutable after upload. Replace by uploading anew and updating references.
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

Match `ext` to encoded bytes; mismatches can cause wrong mime types or upload failures.
MARKDOWN,
            ],
            [
                'title' => 'Metadata fields',
                'content' => <<<'MARKDOWN'
### `filename`

Optional original filename hint; FlyCMS generates a storage name when omitted.

### `code`

Optional stable lookup key — kebab-case or snake_case, e.g. `hero-banner`. Filter by `file_filter.code` on list.

### `information`

Optional JSON metadata — e.g. image `alt`, `width`, `height`, video `duration`. Updates can change `code` and `information` only; binary content is not replaced.
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
                    "1. **Upload first** — create file, then pass `id` to tag/post mutations.\n"
                    ."2. **Reuse assets** — %s finds existing thumbnails.\n"
                    ."3. **Valid base64** — invalid `file_data` fails before upload.\n"
                    ."4. **Verify** — %s confirms `url`, `type`, `size`, `is_uploaded`.\n"
                    .'5. **Delete carefully** — %s only when nothing references the file ID.',
                    McpToolName::quoted($relatedTools['list']),
                    McpToolName::quoted($relatedTools['show']),
                    McpToolName::quoted($relatedTools['delete']),
                ),
            ],
        ];
    }
}
