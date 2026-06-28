<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\AuthorMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class PutAuthorData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'email' => $schema
                ->string()
                ->description('Author email')
                ->required(),
            'display_name' => $schema
                ->string()
                ->description('Display name')
                ->required()
                ->max(100),
            'thumbnail_file_id' => $schema
                ->string()
                ->nullable()
                ->description('Thumbnail file ID (FlyCMS File ID)'),
            'short_bio' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('Author short bio in plain text format'),
            'bio' => $schema
                ->string()
                ->nullable()
                ->max(65535)
                ->description('Author bio in HTML format'),
            'seo_title' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('Seo title (liquid template format, author instance provided)'),
            'seo_description' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('Seo description (liquid template format, author instance provided)'),
        ];
    }
}
