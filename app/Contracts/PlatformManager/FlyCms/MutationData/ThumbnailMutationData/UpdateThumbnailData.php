<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\ThumbnailMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateThumbnailData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'note' => $schema
                ->string()
                ->nullable()
                ->max(5000)
                ->description('Thumbnail note'),
            'status' => $schema
                ->string()
                ->nullable()
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
        ];
    }
}
