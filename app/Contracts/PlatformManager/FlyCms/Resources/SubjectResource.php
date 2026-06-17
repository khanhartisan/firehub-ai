<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class SubjectResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'subjects';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Subject unique ID'),
            'branch_id' => $schema->string()->description('Subject branch ID'),
            'code' => $schema->string()->description('Subject unique code'),
            'title' => $schema->string()->description('Subject title'),
            'description' => $schema->string()->description('Subject description'),
            'created_at' => $schema->string()->description('Subject created at'),
            'updated_at' => $schema->string()->description('Subject updated at'),
        ];
    }
}