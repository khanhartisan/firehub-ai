<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\PartMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdatePartData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'sequence' => $schema->integer()->nullable()->min(1)->max(9999)->description('Part sequence within the subject'),
            'title' => $schema->string()->nullable()->max(255)->description('Part title'),
            'description' => $schema->string()->nullable()->max(255)->description('Part description'),
        ];
    }
}
