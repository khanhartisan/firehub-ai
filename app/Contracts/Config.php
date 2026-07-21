<?php

namespace App\Contracts;

use App\Utils\StructuredDataFromSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Arr;

abstract class Config implements Clonable, ProvidesJsonSchema, Serializable
{
    use \App\Concerns\Serializable;

    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    public function get(string $path): mixed
    {
        return Arr::get($this->getConfig(), $path);
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

    public function clone(): Clonable
    {
        return new static($this->getConfig());
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
