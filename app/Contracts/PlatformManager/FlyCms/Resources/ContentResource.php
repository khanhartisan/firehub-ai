<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ContentResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'contents';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Content unique ID'),
            'part_id' => $schema->string()->description('Part ID this content belongs to'),
            'post_id' => $schema->string()->description('Linked FlyCMS post ID')->nullable(),
            'lang' => $schema->string()->description('Content language'),
            'title' => $schema->string()->description('Content title')->nullable(),
            'description' => $schema->string()->description('Content description')->nullable(),
            'content' => $schema->string()->description('Content body')->nullable(),
            'created_at' => $schema->string()->description('Content created at'),
            'updated_at' => $schema->string()->description('Content updated at'),
        ];
    }
}
