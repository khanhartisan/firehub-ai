<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateRoleData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->nullable()
                ->description('Role name'),
            'abilities' => $schema
                ->array()
                ->items($schema->string())
                ->nullable()
                ->description('Abilities'),
        ];
    }
}