<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class TagResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'website_tag';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema
                ->string()
                ->description('Tag unique ID'),
            'website_id' => $schema
                ->string()
                ->description('Website ID that the menu belongs to'),
            'is_featured' => $schema
                ->boolean()
                ->description('Is this tag a featured tag?'),
            'name' => $schema
                ->string()
                ->description('Tag name'),
            'description' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Tag description'),
            'slug' => $schema
                ->string()
                ->description('Tag URL slug in kebab-case'),
            'seo_title' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Seo title (liquid template format)'),
            'seo_description' => $schema
                ->string()
                ->nullable()
                ->description('Seo description'),
            'seo_h1' => $schema
                ->string()
                ->nullable()
                ->description('H1 html tag content (liquid template format)'),
            'content' => $schema
                ->string()
                ->nullable()
                ->description('Tag body content in HTML format'),
            'public_posts_count' => $schema
                ->integer()
                ->description('Posts count'),
            'created_at' => $schema
                ->string()
                ->description('Tag created at'),
            'updated_at' => $schema
                ->string()
                ->description('Tag updated at'),

            'thumbnail_file_id' => $schema
                ->string()
                ->nullable()
                ->description('Thumbnail file ID (FlyCMS File ID)'),
            'thumbnailFile' => $schema
                ->object(FileResource::getMcpOutputSchema($schema))
                ->nullable()
        ];
    }
}