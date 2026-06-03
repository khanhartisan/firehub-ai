<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class TagFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->nullable()
                ->description('Search tags by name')
        ];
    }
}