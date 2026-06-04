<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreatePageData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'website_id' => $schema
                ->string()
                ->required()
                ->description('Page website id'),
            'slug' => $schema
                ->string()
                ->required()
                ->description('Page URL slug in kebab-case'),
            'title' => $schema
                ->string()
                ->max(255)
                ->required()
                ->description('Page title'),
            'seo_title' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Page SEO title in liquid template format'),
            'seo_description' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Page SEO description'),
            'content' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Page content in liquid template format'),
        ];
    }
}