<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\MetableResource;
use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class WebsiteResource extends Resource implements MetableResource
{
    public static function resourceNamespace(): string
    {
        return 'websites';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema
                ->string()
                ->description('Website unique ID'),
            'status' => $schema
                ->string()
                ->description('Website status'),
            'name' => $schema
                ->string()
                ->description('Website name (internal display only)'),
            'domains_count' => $schema
                ->integer()
                ->description('Number of domains pointed to the website'),
            'public_posts_count' => $schema
                ->integer()
                ->description('Number of website public posts'),
            'asset_route' => $schema
                ->string()
                ->nullable()
                ->description('Website asset route'),
            'page_route' => $schema
                ->string()
                ->nullable()
                ->description('Website custom page route'),
            'post_route' => $schema
                ->string()
                ->nullable()
                ->description('Website post route'),
            'website_tag_route' => $schema
                ->string()
                ->nullable()
                ->description('Website tag route'),
            'theme_id' => $schema
                ->string()
                ->nullable()
                ->description('Theme ID assigned to the website'),
            'traffic_statistics' => $schema
                ->object()
                ->nullable()
                ->description('Website traffic statistics'),
            'created_at' => $schema
                ->string()
                ->description('Website created at'),
            'updated_at' => $schema
                ->string()
                ->description('Website updated at'),
            'meta' => $schema
                ->object(static::getMetaSchema($schema))
                ->description('Website meta data'),
        ];
    }

    public static function getMetaSchema(JsonSchema $schema): array
    {
        return [
            'site-name' => $schema
                ->string()
                ->nullable()
                ->description('Website name (public display)'),
            'home-seo-title' => $schema
                ->string()
                ->nullable()
                ->description('Home SEO Title (liquid template format)'),
            'home-seo-description' => $schema
                ->string()
                ->nullable()
                ->description('Home SEO Description (liquid template format)'),
            'tag-seo-title' => $schema
                ->string()
                ->nullable()
                ->description('Tag page SEO Title (liquid template format)'),
            'tag-seo-description' => $schema
                ->string()
                ->nullable()
                ->description('Tag page SEO Description (liquid template format)'),
            'page-seo-title' => $schema
                ->string()
                ->nullable()
                ->description('Custom Page SEO Title (liquid template format)'),
            'page-seo-description' => $schema
                ->string()
                ->nullable()
                ->description('Custom Page SEO Description (liquid template format)'),
            'post-seo-title' => $schema
                ->string()
                ->nullable()
                ->description('Custom Post SEO Title (liquid template format)'),
            'post-seo-description' => $schema
                ->string()
                ->nullable()
                ->description('Custom Post SEO Description (liquid template format)'),
            'items-per-page' => $schema
                ->string()
                ->nullable()
                ->description('Numeric, default number of items per page (pagination'),
            'query-page-name' => $schema
                ->string()
                ->nullable()
                ->description('Query param for current page (pagination), default is "page"'),
            'query-limit-name' => $schema
                ->string()
                ->nullable()
                ->description('Query param for custom limit of items per page (pagination), default is "limit"'),
            'theme-config' => $schema
                ->string()
                ->nullable()
                ->description('Theme configuration (usually a JSON-encoded string)'),
        ];
    }
}
