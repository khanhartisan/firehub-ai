<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class BaseTagResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'tags';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Base tag unique ID'),
            'name' => $schema->string()->description('Base tag name'),
        ];
    }
}