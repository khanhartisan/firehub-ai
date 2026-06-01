<?php

namespace App\Contracts\Platforms\FlyCms;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class Config extends \App\Contracts\Platforms\Config
{
    public function getBaseUrl(): ?string
    {
        return $this->config['base_url'] ?? null;
    }

    public function getApiKey(): ?string
    {
        return $this->config['api_key'] ?? null;
    }

    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'base_url' => $schema
                ->string()
                ->required(),
            'api_key' => $schema
                ->string()
                ->required(),
        ];
    }
}
