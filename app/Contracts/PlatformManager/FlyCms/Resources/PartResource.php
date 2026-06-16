<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class PartResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'parts';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Part unique ID'),
            'subject_id' => $schema->string()->description('Subject ID this part belongs to'),
            'sequence' => $schema->integer()->description('Part sequence within the subject'),
            'title' => $schema->string()->description('Part title')->nullable(),
            'description' => $schema->string()->description('Part description')->nullable(),
            'created_at' => $schema->string()->description('Part created at'),
            'updated_at' => $schema->string()->description('Part updated at'),
        ];
    }
}
