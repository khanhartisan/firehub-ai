<?php

namespace App\Contracts\CommonData;

use App\Contracts\Serializable;

class SemanticContext implements Serializable
{
    use \App\Concerns\Serializable;

    protected array $data = [];

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key): ?array
    {
        if (!$data = ($this->has($key) ? $this->data[$key] : null)) {
            return null;
        }

        return [
            'description' => $data['description'],
            'value' => $this->normalizeValue($data['value']),
        ];
    }

    public function set(
        string $key,
        string $description,
        string|int|float|array|Serializable|null $value): static
    {
        if (! self::isSerializableValue($value)) {
            throw new \InvalidArgumentException('SemanticContext value contains non-serializable nested data.');
        }

        $this->data[$key] = [
            'description' => $description,
            'value' => $value,
        ];

        return $this;
    }

    public static function fromArray(array $data): static
    {
        $context = new static();

        foreach ($data as $key => $_data) {
            if (! is_array($_data)
                or ! isset($_data['description'])
                or ! is_string($_data['description'])
                or ! array_key_exists('value', $_data)
                or (
                    ! self::isSerializableValue($_data['value'])
                )
            ) {
                continue;
            }

            $context->set($key, $_data['description'], $_data['value']);
        }

        return $context;
    }

    public function toArray(): array
    {
        $data = $this->data;

        foreach ($data as $key => $_data) {
            $data[$key]['value'] = $this->normalizeValue($_data['value']);
        }

        return $data;
    }

    protected static function isSerializableValue(mixed $value): bool
    {
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || $value instanceof Serializable) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $nested) {
            if (! self::isSerializableValue($nested)) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Serializable) {
            return $value->toArray();
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $nested) {
            $normalized[$key] = $this->normalizeValue($nested);
        }

        return $normalized;
    }
}