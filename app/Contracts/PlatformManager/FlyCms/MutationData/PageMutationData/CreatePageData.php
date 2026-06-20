<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\PageGuidelinesResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreatePageData extends MutationData
{
    protected string $pageGuidelinesResourceName;

    public function toJsonSchema(JsonSchema $schema): array
    {
        $guidelines = $this->getPageGuidelinesResourceName();

        return [
            'website_id' => $schema
                ->string()
                ->required()
                ->description('Page website id, get from channel reference'),
            'slug' => $schema
                ->string()
                ->required()
                ->description('Page URL slug in kebab-case. See resource: '.$guidelines),
            'title' => $schema
                ->string()
                ->max(255)
                ->required()
                ->description('Page title'),
            'seo_title' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Page SEO title in liquid template format. See resource: '.$guidelines),
            'seo_description' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Page SEO description in liquid template format. See resource: '.$guidelines),
            'content' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Page content in liquid template format. See resource: '.$guidelines),
        ];
    }

    protected function getPageGuidelinesResourceName(): string
    {
        return $this->pageGuidelinesResourceName ??= new PageGuidelinesResource()->name();
    }
}
