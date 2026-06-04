<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdatePostData extends CreatePostData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        $data = parent::toJsonSchema($schema);
        unset($data['website_id']);

        $data['slug'] = $schema
            ->string()
            ->nullable()
            ->description('Post URL slug in kebab-case');

        $data['title'] = $schema
            ->string()
            ->nullable()
            ->max(255)
            ->description('Post title');

        return $data;
    }
}