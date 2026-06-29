<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class AuthorFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'email' => $schema->string()->description('Filter by email'),
        ];
    }
}