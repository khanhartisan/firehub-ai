<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\WebsiteGuidelinesResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateWebsiteData extends MutationData
{
    protected string $websiteGuidelinesResourceName;

    public function toJsonSchema(JsonSchema $schema): array
    {
        $guidelines = $this->getWebsiteGuidelinesResourceName();

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
                ->description('URI path for static assets (js, css...). Example: /assets/{path}. See resource: '.$guidelines),
            'page_route' => $schema
                ->string()
                ->nullable()
                ->description('URI path for custom pages. Example: /page/{page}. See resource: '.$guidelines),
            'website_tag_route' => $schema
                ->string()
                ->nullable()
                ->description('URI path for tags. Example: /tag/{websiteTag}. See resource: '.$guidelines),
            'post_route' => $schema
                ->string()
                ->nullable()
                ->description('URI path for posts. Example: /post/{post}. See resource: '.$guidelines),
            'theme_id' => $schema
                ->string()
                ->nullable()
                ->description('Theme ID to assign to the website. See resource: '.$guidelines),
        ];
    }

    protected function getWebsiteGuidelinesResourceName(): string
    {
        return $this->websiteGuidelinesResourceName ??= new WebsiteGuidelinesResource()->name();
    }
}
