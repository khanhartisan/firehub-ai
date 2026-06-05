<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateRoleData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->required()
                ->description('Role name'),
            'abilities' => $schema
                ->array()
                ->items($schema->string())
                ->required()
                ->description('Abilities'),
        ];
    }
}