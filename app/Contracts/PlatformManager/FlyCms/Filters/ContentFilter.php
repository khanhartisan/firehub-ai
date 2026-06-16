<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ContentFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'part_id' => $schema
                ->string()
                ->required()
                ->description('Filter by part ID'),
            'post_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter by linked FlyCMS post ID'),
        ];
    }
}
