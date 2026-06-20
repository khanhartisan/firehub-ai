<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use App\Contracts\PlatformManager\FlyCms\Resources\MenuResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\MenuGuidelinesResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateMenuData extends MutationData
{
    protected string $menuGuidelinesResourceName;

    public function toJsonSchema(JsonSchema $schema): array
    {
        $guidelines = $this->getMenuGuidelinesResourceName();

        return [
            'key' => $schema
                ->string()
                ->nullable()
                ->description('Menu key in kebab-case. By default all themes would support 2 keys: main and footer. Other themes may support other keys, check website theme for more details. See resource: '.$guidelines),
            'items' => $schema
                ->array()
                ->nullable()
                ->items(MenuResource::itemsSchemaType($schema))
                ->description('Menu items. See resource: '.$guidelines),
        ];
    }

    protected function getMenuGuidelinesResourceName(): string
    {
        return $this->menuGuidelinesResourceName ??= new MenuGuidelinesResource()->name();
    }
}