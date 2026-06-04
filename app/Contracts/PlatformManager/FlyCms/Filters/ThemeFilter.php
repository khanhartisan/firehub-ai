<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ThemeFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema
                ->string()
                ->nullable()
                ->description('List of theme IDs separated by commas'),
            'search' => $schema
                ->string()
                ->nullable()
                ->description('Search themes by name'),
        ];
    }
}
