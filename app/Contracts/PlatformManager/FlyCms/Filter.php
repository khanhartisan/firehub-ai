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

    public function toArray(): array
    {
        return $this->getFilterData();
    }

    public static function fromArray(array $data): static
    {
        return new static($data);
    }
}
