<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class BaseTagFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Base tag name (exact match)'),
        ];
    }
}