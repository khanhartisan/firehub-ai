<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateUserData extends CreateUserData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        $data = parent::toJsonSchema($schema);

        $data['name'] = $schema
            ->string()
            ->nullable()
            ->max(60)
            ->description('User display name');

        $data['email'] = $schema
            ->string()
            ->nullable()
            ->format('email')
            ->description('User email address');

        $data['password'] = $schema
            ->string()
            ->nullable()
            ->description('User password');

        return $data;
    }
}
