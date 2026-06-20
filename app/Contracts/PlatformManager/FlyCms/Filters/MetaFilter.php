<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class MetaFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'metable_type' => $schema
                ->string()
                ->nullable()
                ->description('Filter by parent resource type (e.g. website)'),
            'metable_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter by parent resource ID (e.g. website ID from channel reference)'),
            'key' => $schema
                ->string()
                ->nullable()
                ->description('Filter by meta key'),
        ];
    }
}
