<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class PostFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'website_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter by website id (get from channel reference)'),
            'ids' => $schema
                ->string()
                ->nullable()
                ->description('List of post IDs separated by commas'),
            'restriction' => $schema
                ->integer()
                ->min(0)
                ->max(2)
                ->nullable()
                ->description('Filter posts by restriction'),
            'search' => $schema
                ->string()
                ->nullable()
                ->description('Search posts by text'),
            'slug' => $schema
                ->string()
                ->nullable()
                ->description('Find post by slug'),
            'visibility' => $schema
                ->string()
                ->nullable()
                ->description('Filter posts by visibility')
                ->enum(['public', 'private']),
            'tag_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter posts by tag ID'),
        ];
    }
}