<?php

namespace App\Contracts;

use Illuminate\Contracts\JsonSchema\JsonSchema;

interface ProvidesJsonSchema
{
    public function toJsonSchema(JsonSchema $schema): array;
}
