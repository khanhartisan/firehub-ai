<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class MetaResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'meta';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema
                ->string()
                ->description('Meta unique ID'),
            'metable_type' => $schema
                ->string()
                ->enum(['website'])
                ->description('Parent resource type (e.g. website)'),
            'metable_id' => $schema
                ->string()
                ->description('Parent resource ID'),
            'key' => $schema
                ->string()
                ->description('Meta key'),
            'value' => $schema
                ->string()
                ->nullable()
                ->description('Meta value'),
            'created_at' => $schema
                ->string()
                ->description('Meta created at'),
            'updated_at' => $schema
                ->string()
                ->description('Meta updated at'),
        ];
    }
}
