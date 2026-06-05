<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateWebsiteData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema
                ->string()
                ->required()
                ->enum(['active', 'inactive']),
            'name' => $schema
                ->string()
                ->required()
                ->description('Website name (for internal display)'),
            'asset_route' => $schema
                ->string()
                ->nullable()
                ->description('URI path for static assets (js, css...). Example: /assets/{path}'),
            'page_route' => $schema
                ->string()
                ->nullable()
                ->description('URI path for custom pages. Example: /page/{page}'),
            'website_tag_route' => $schema
                ->string()
                ->nullable()
                ->description('URI path for tags. Example: /tag/{websiteTag}'),
            'post_route' => $schema
                ->string()
                ->nullable()
                ->description('URI path for posts. Example: /post/{post}'),
            'theme_id' => $schema
                ->string()
                ->nullable()
                ->description('Theme ID to assign to the website'),
        ];
    }
}
