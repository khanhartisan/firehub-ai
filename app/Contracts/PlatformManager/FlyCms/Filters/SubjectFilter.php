<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class SubjectFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'code' => $schema
                ->string()
                ->nullable()
                ->description('Subject unique code')
        ];
    }
}