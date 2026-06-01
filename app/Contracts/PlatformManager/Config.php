<?php

namespace App\Contracts\PlatformManager;

use App\Contracts\ProvidesJsonSchema;
use App\Contracts\Serializable;
use App\Utils\StructuredDataFromSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

abstract class Config implements ProvidesJsonSchema, Serializable
{
    use \App\Concerns\Serializable;

    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config = []): static
    {
        $this->config = StructuredDataFromSchema::fromSchema(
            $this->toJsonSchema(new JsonSchemaTypeFactory()),
            $config
        );

        return $this;
    }

    public function toArray(): array
    {
        return $this->getConfig();
    }

    public static function fromArray(array $data): static
    {
        return new static($data);
    }
}
