<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ThemeResource extends Resource
{
    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema
                ->string()
                ->description('Theme unique ID'),
            'name' => $schema
                ->string()
                ->description('Theme name'),
            'description' => $schema
                ->string()
                ->nullable()
                ->description('Theme description'),
            'guidelines' => $schema
                ->string()
                ->nullable()
                ->description('Theme usage guidelines for editors and integrations'),
            'key' => $schema
                ->string()
                ->description('Theme identifier key'),
            'dev_mode' => $schema
                ->boolean()
                ->description('Whether the theme is in development mode'),
            'websites_count' => $schema
                ->integer()
                ->description('Number of websites using this theme'),
            'created_at' => $schema
                ->string()
                ->description('Theme created at'),
            'updated_at' => $schema
                ->string()
                ->description('Theme updated at'),
        ];
    }
}
