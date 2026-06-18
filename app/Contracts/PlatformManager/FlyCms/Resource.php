<?php

namespace App\Contracts\PlatformManager\FlyCms;

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
        $this->setData($resourceData);
    }

    abstract public static function resourceNamespace(): string;

    public function getData(): array
    {
        return $this->resourceData;
    }

    public function setData(array $resourceData): static
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

    public function get(string $key): mixed
    {
        return $this->resourceData[$key] ?? null;
    }

    public function set(string $key, mixed $value): static
    {
        $this->resourceData[$key] = $value;
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
