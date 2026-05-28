<?php

namespace App\Contracts\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;

interface StructuredMcpResource
{
    public function getMcpOutputSchema(JsonSchema $schema): array;

    public function toMcpStructuredData(): array;
}