<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UserResource extends Resource
{
    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema
                ->string()
                ->description('User unique ID'),
            'name' => $schema
                ->string()
                ->description('User display name'),
            'email' => $schema
                ->string()
                ->description('User email address'),
            'api_key' => $schema
                ->string()
                ->nullable()
                ->description('User API key (visible to managers or the user themselves)'),
            'created_at' => $schema
                ->string()
                ->description('User created at'),
            'updated_at' => $schema
                ->string()
                ->description('User updated at'),
        ];
    }
}
