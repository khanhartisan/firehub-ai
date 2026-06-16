<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\SubjectMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateSubjectData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema->string()->required()->description('Branch ID'),
            'code' => $schema->string()->required()->description('Subject unique code'),
            'title' => $schema->string()->required()->description('Subject title'),
        ];
    }
}