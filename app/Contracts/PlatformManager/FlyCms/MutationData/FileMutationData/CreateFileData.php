<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateFileData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'ext' => $schema
                ->string()
                ->required()
                ->description('File extension, ie: jpg, jpeg, png...')
                ->enum(['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm']),
            'filename' => $schema
                ->string()
                ->nullable()
                ->description('Original filename, leave null for auto generation'),
            'code' => $schema
                ->string()
                ->nullable()
                ->description('File custom defined unique code'),
            'information' => $schema
                ->object()
                ->nullable()
                ->description('Additional meta information about the file')
        ];
    }
}