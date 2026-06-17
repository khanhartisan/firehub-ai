<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ThumbnailResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'thumbnails';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Thumbnail unique ID'),
            'subject_id' => $schema->string()->description('Subject ID this thumbnail belongs to'),
            'status' => $schema
                ->string()
                ->description('Thumbnail progress status')
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
            'files_count' => $schema->integer()->description('Number of attached files'),
            'note' => $schema->string()->nullable()->description('Thumbnail note'),
            'created_at' => $schema->string()->description('Thumbnail created at'),
            'updated_at' => $schema->string()->description('Thumbnail updated at'),
        ];
    }
}
