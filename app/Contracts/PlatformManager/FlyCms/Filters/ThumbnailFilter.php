<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ThumbnailFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'subject_id' => $schema
                ->string()
                ->required()
                ->description('Filter by subject ID'),
            'status' => $schema
                ->string()
                ->nullable()
                ->description('Filter by thumbnail progress status')
                ->enum([
                    'draft',
                    'pending',
                    'awaiting',
                    'processing',
                    'awaiting-approval',
                    'partial',
                    'active',
                    'inactive',
                    'rejected',
                    'archived',
                ]),
            'user_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter by FlyCMS user ID'),
            'ids' => $schema
                ->string()
                ->nullable()
                ->description('List of thumbnail IDs separated by commas'),
        ];
    }
}
