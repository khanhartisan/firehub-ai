<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateTagData extends CreateTagData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        $data = parent::toJsonSchema($schema);

        unset($data['website_id']);

        $data['name'] = $schema
            ->string()
            ->nullable()
            ->description('Tag name');

        $data['slug'] = $schema
            ->string()
            ->nullable()
            ->description('Tag slug in kebab-case');

        return $data;
    }
}