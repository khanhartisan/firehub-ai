<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class MenuFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'website_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter by website id (get from channel reference)'),
        ];
    }
}
