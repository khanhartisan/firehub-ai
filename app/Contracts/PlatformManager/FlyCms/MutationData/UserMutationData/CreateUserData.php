<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateUserData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->required()
                ->max(60)
                ->description('User display name'),
            'email' => $schema
                ->string()
                ->required()
                ->format('email')
                ->description('User email address'),
            'password' => $schema
                ->string()
                ->required()
                ->description('User password'),
            'role_id' => $schema
                ->string()
                ->nullable()
                ->description('Role ID'),
            'api_key' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('User API key'),
        ];
    }
}
