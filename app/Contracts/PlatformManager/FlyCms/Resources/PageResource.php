<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class PageResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'pages';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Page unique ID'),
            'website_id' => $schema
                ->string()
                ->description('Website ID that the page belongs to'),
            'slug' => $schema->string()->description('Page URL slug'),
            'title' => $schema->string()->description('Page title'),
            'seo_title' => $schema
                ->string()
                ->description('Page SEO title in liquid template format'),
            'seo_description' => $schema
                ->string()
                ->description('Page SEO description in liquid template format'),
            'content' => $schema
                ->string()
                ->description('Page content in liquid template format'),
            'created_at' => $schema->string()->description('Page created at'),
            'updated_at' => $schema->string()->description('Page updated at'),
        ];
    }
}