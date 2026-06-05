<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class RoleFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'search' => $schema
                ->string()
                ->nullable()
                ->description('Search role')
        ];
    }
}