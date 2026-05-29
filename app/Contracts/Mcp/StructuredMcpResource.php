<?php

namespace App\Contracts\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;

interface StructuredMcpResource
{
    public static function getMcpOutputSchema(JsonSchema $schema): array;

    public function toMcpStructuredData(): array;
}