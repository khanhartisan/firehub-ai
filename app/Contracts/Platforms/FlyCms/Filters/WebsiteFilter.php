<?php

namespace App\Contracts\Platforms\FlyCms\Filters;

use App\Contracts\Platforms\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class WebsiteFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema
                ->string()
                ->nullable()
                ->description('List of website IDs separated by a comma'),
            'search' => $schema
                ->string()
                ->nullable()
                ->description('Search website'),
        ];
    }
}