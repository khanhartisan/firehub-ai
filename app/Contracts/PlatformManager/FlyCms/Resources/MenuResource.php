<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

class MenuResource extends Resource
{
    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema
                ->string()
                ->description('Menu unique ID'),
            'website_id' => $schema
                ->string()
                ->description('Website ID that the menu belongs to'),
            'key' => $schema
                ->string()
                ->description('Menu key (ie: main, footer,...)'),
            'items' => $schema
                ->array()
                ->items(static::itemsSchemaType($schema))
                ->description('Menu items'),
            'created_at' => $schema
                ->string()
                ->description('Website created at'),
            'updated_at' => $schema
                ->string()
                ->description('Website updated at'),
        ];
    }

    public static function itemsSchemaType(JsonSchema $schema, int $depth = 3): Type
    {
        $properties = [
            'text' => $schema
                ->string()
                ->description('Menu text')
                ->required(),
            'link' => $schema
                ->string()
                ->description('Menu link, 3 formats are supported. First format is: Full URL with scheme. Second format is: Relative path (ie: /example-path). And final format is: link:website_tag,{tagId}, ie: link:website_tag,01hw720nn5ef2dztvftfg5m47q')
                ->required(),
            'new_tab' => $schema
                ->integer()
                ->nullable()
                ->description('Open new tab when clicking to the link or not. 0 is open in the current tab, 1 is open in the new tab'),
        ];

        if ($depth) {
            $properties['items'] = $schema
                ->array()
                ->nullable()
                ->items(static::itemsSchemaType($schema, $depth - 1));
        }

        return $schema->object($properties);
    }
}