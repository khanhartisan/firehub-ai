<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;

class UpdateFileData extends CreateFileData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        $data = parent::toJsonSchema($schema);
        unset($data['ext']);
        unset($data['filename']);
        return $data;
    }
}