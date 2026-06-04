<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class FileFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema
                ->string()
                ->nullable()
                ->description('List of file IDs separated by commas'),
            'post_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter out files that is used in a FlyCMS Post ID (get from publication reference)'),
            'code' => $schema
                ->string()
                ->nullable()
                ->description('Filter by the unique custom defined code'),
            'key' => $schema
                ->string()
                ->nullable()
                ->description('Filter by file key (path)'),
            'type' => $schema
                ->string()
                ->nullable()
                ->description('Filter by file type')
                ->enum(['image', 'video', 'unknown'])
        ];
    }
}