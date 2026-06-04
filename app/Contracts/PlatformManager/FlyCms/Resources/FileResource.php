<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class FileResource extends Resource
{
    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema
                ->string()
                ->description('File unique ID'),
            'code' => $schema
                ->string()
                ->description('File custom unique identifier code'),
            'user_id' => $schema
                ->string()
                ->description('FlyCMS User ID'),
            'key' => $schema
                ->string()
                ->description('File key (path in the storage)'),
            'type' => $schema
                ->string()
                ->description('File type')
                ->enum(['image', 'video', 'unknown']),
            'mime' => $schema
                ->string()
                ->description('File mime type'),
            'size' => $schema
                ->integer()
                ->description('File size in bytes'),
            'information' => $schema
                ->object()
                ->description('File additional information'),
            'is_uploaded' => $schema
                ->boolean()
                ->description('File is uploaded to server'),
            'url' => $schema
                ->string()
                ->nullable()
                ->description('File URL'),
        ];
    }
}