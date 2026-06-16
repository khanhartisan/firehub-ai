<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\ContentMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateContentData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->nullable()->max(255)->description('Content title'),
            'description' => $schema->string()->nullable()->max(255)->description('Content description'),
            'content' => $schema->string()->nullable()->max(65535)->description('Content body'),
            'post_id' => $schema->string()->nullable()->description('Linked FlyCMS post ID'),
            'note' => $schema->string()->nullable()->max(5000)->description('Content note'),
        ];
    }
}
