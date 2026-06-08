<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class RoleResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'roles';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Role unique ID'),
            'name' => $schema->string()->description('Role name'),
            'abilities' => $schema
                ->array()
                ->unique()
                ->items($schema->string())
                ->description('Abilities'),
            'created_at' => $schema
                ->string()
                ->description('Role created at'),
            'updated_at' => $schema
                ->string()
                ->description('Role updated at'),
        ];
    }
}