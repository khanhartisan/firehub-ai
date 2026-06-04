<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdatePageData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema
                ->string()
                ->nullable()
                ->description('Page URL slug in kebab-case'),
            'title' => $schema
                ->string()
                ->max(255)
                ->nullable()
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