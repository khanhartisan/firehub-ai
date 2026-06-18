<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\BaseTagMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateBaseTagData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->required()
                ->max(255)
                ->description('Base tag name'),
        ];
    }
}