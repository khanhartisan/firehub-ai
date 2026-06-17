<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\ThumbnailMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateThumbnailData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'subject_id' => $schema
                ->string()
                ->required()
                ->description('Subject ID'),
            'note' => $schema
                ->string()
                ->nullable()
                ->max(5000)
                ->description('Thumbnail note'),
        ];
    }
}
