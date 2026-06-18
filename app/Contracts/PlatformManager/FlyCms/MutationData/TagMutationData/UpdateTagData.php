<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateTagData extends CreateTagData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        $data = parent::toJsonSchema($schema);

        unset($data['website_id']);
        unset($data['name']);

        $data['display_name'] = $schema
            ->string()
            ->nullable()
            ->description('Tag display name to show the the end users');

        $data['slug'] = $schema
            ->string()
            ->nullable()
            ->description('Tag slug in kebab-case');

        return $data;
    }

    public function getData(): ?array
    {
        $data = parent::getData();

        if (isset($data['display_name'])) {
            $data['name'] = $data['display_name'];
            unset($data['display_name']);
        }

        return $data;
    }
}