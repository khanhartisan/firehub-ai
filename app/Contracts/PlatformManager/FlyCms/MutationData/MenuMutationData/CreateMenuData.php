<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateMenuData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'website_id' => $schema
                ->string()
                ->required()
                ->description('Website ID (get from channel reference)'),
            'key' => $schema
                ->string()
                ->required()
                ->description('Menu key in kebab-case. By default all themes would support 2 keys: main and footer. Other themes may support other keys, check website theme for more details.'),
            'items' => $schema
                ->array()
                ->items(MenuResource::itemsSchemaType($schema)),
        ];
    }
}