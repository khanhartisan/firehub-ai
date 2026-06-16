<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class PartFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'subject_id' => $schema
                ->string()
                ->required()
                ->description('Filter by subject ID'),
            'sequence' => $schema
                ->integer()
                ->nullable()
                ->description('Filter by part sequence within the subject'),
        ];
    }
}
