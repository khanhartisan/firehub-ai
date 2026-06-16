<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\SubjectMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateSubjectData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->nullable()->description('Subject unique code'),
            'title' => $schema->string()->nullable()->description('Subject title'),
        ];
    }
}