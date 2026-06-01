<?php

namespace App\Contracts\PlatformManager\FlyCms;

use Illuminate\Contracts\JsonSchema\JsonSchema;

interface MetableResource
{
    public static function getMetaSchema(JsonSchema $schema): array;
}
