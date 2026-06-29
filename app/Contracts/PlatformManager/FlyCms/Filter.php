<?php

namespace App\Contracts\PlatformManager\FlyCms;

use App\Contracts\ProvidesJsonSchema;
use App\Contracts\Serializable;
use App\Utils\StructuredDataFromSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

abstract class Filter implements ProvidesJsonSchema, Serializable
{
    use \App\Concerns\Serializable;

    protected array $filterData = [];

    public function __construct(array $filterData = []) {}

    public function getFilterData(): array
    {
        return $this->filterData;
    }

    public function setFilterData(array $filterData): static
    {
        $this->filterData = StructuredDataFromSchema::fromSchema(
            $this->toJsonSchema(new JsonSchemaTypeFactory),
            $filterData
        );

        return $this;
    }

    public function set(string $key, mixed $value): static
    {
        $this->filterData[$key] = $value;
        $this->setFilterData($this->getFilterData());
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->filterData[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->getFilterData();
    }

    public static function fromArray(array $data): static
    {
        return new static($data);
    }
}
