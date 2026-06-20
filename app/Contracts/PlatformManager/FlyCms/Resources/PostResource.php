<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class PostResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'posts';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Post unique ID'),
            'website_id' => $schema->string()->description('Website ID'),
            'slug' => $schema->string()->description('Post URL slug'),
            'title' => $schema->string()->description('Post title'),
            'description' => $schema->string()->description('Post description'),
            'content' => $schema->string()->description('Post content in liquid template format'),
            'seo_title' => $schema->string()->description('Post SEO title'),
            'seo_description' => $schema->string()->description('Post SEO description'),
            'visibility' => $schema
                ->string()
                ->description('Post visibility')
                ->enum(['public', 'private']),
            'restriction' => $schema
                ->integer()
                ->min(0)
                ->max(2)
                ->description('0: No restriction, 1: Restricted to show in the tag pages only, 2: Not showing anywhere in the website but accessible by URL'),
            'lang' => $schema
                ->string()
                ->nullable()
                ->description('Post language, 2 letter ISO 639-1, special value "default" for default language according to the website.'),
            'created_at' => $schema
                ->string()
                ->description('Post created at'),
            'updated_at' => $schema
                ->string()
                ->description('Post updated at'),
            'published_at' => $schema
                ->string()
                ->description('Post published at'),

            'tags' => $schema
                ->array()
                ->items(
                    $schema->object(TagResource::getMcpOutputSchema($schema))
                ),

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