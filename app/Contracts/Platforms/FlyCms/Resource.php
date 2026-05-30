<?php

namespace App\Contracts\Platforms\FlyCms;

use App\Contracts\Mcp\StructuredMcpResource;
use App\Contracts\Serializable;
use App\Utils\StructuredDataFromSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

abstract class Resource implements Serializable, StructuredMcpResource
{
    use \App\Concerns\Serializable;

    protected array $resourceData = [];

    public function __construct(array $resourceData)
    {
        $this->setResourceData($resourceData);
    }

    public function getResourceData(): array
    {
        return $this->resourceData;
    }

    public function setResourceData(array $resourceData): static
    {
        $this->resourceData = $resourceData;

        // Convert meta data to key->value format
        if (isset($resourceData['meta']) and is_array($resourceData['meta'])) {
            $meta = [];
            foreach ($resourceData['meta'] as $key => $metaData) {
                if (is_string($metaData) or is_int($metaData) or is_float($metaData) or is_bool($metaData)) {
                    $meta[$key] = $metaData;

                    continue;
                }

                if (is_array($metaData)
                    and isset($metaData['key'])
                    and isset($metaData['value'])
                ) {
                    $meta[$metaData['key']] = $metaData['value'];
                }
            }
            $this->resourceData['meta'] = $meta;
        }

        return $this;
    }

    public function toMcpStructuredData(): array
    {
        return StructuredDataFromSchema::fromSchema(
            static::getMcpOutputSchema(new JsonSchemaTypeFactory),
            $this->resourceData
        );
    }

    public function toArray(): array
    {
        return $this->resourceData;
    }

    public static function fromArray(array $data): static
    {
        return new static($data);
    }
}
