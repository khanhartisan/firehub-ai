<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\Config;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class HitlPlatformConfig extends Config
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [];
    }
}