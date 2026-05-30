<?php

namespace App\Contracts\Platforms\FlyCms;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class Config extends \App\Contracts\Platforms\Config
{
    public function __construct(array $config = [])
    {
        if (! isset($config['base_url'])) {
            throw new \InvalidArgumentException('base_url is required.');
        }

        if (! isset($config['api_key'])) {
            throw new \InvalidArgumentException('api_key is required.');
        }

        parent::__construct($config);
    }

    public function getBaseUrl(): string
    {
        return $this->config['base_url'];
    }

    public function getApiKey(): string
    {
        return $this->config['api_key'];
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
