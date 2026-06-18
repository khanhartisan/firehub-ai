<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class TagFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'tag_id' => $schema->string()->nullable()->description('Base tag id'),
            'website_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter by website id (get from channel reference)'),
            'name' => $schema
                ->string()
                ->nullable()
                ->description('Search tags by name')
        ];
    }
}