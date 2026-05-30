<?php

namespace App\Contracts\Platforms\FlyCms;

use Illuminate\Contracts\JsonSchema\JsonSchema;

interface MetableResource
{
    public static function getMetaSchema(JsonSchema $schema): array;
}
