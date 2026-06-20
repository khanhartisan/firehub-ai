<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\FileGuidelinesResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateFileData extends MutationData
{
    protected string $fileGuidelinesResourceName;

    public function toJsonSchema(JsonSchema $schema): array
    {
        $guidelines = $this->getFileGuidelinesResourceName();

        return [
            'ext' => $schema
                ->string()
                ->required()
                ->description('File extension, ie: jpg, jpeg, png... See resource: '.$guidelines)
                ->enum(['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm']),
            'filename' => $schema
                ->string()
                ->nullable()
                ->description('Original filename, leave null for auto generation. See resource: '.$guidelines),
            'code' => $schema
                ->string()
                ->nullable()
                ->description('File custom defined unique code. See resource: '.$guidelines),
            'information' => $schema
                ->object()
                ->nullable()
                ->description('Additional meta information about the file. See resource: '.$guidelines),
        ];
    }

    protected function getFileGuidelinesResourceName(): string
    {
        return $this->fileGuidelinesResourceName ??= new FileGuidelinesResource()->name();
    }
}
