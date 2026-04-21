<?php

namespace App\Contracts\CommonData;

use App\Contracts\Serializable;

class SemanticContext implements Serializable
{
    use \App\Concerns\Serializable;

    protected ?array $keys = null;

    protected array $data = [];

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $reflection = new \ReflectionClass($this);
        $bootMethods = array_filter(
            $reflection->getMethods(),
            static fn (\ReflectionMethod $method): bool => str_starts_with($method->getName(), 'boot')
                && $method->getNumberOfRequiredParameters() === 0
                && ! $method->isStatic()
        );

        usort(
            $bootMethods,
            static fn (\ReflectionMethod $a, \ReflectionMethod $b): int => strcmp($a->getName(), $b->getName())
        );

        foreach ($bootMethods as $method) {
            $method->invoke($this);
        }
    }

    protected function getKeys(): ?array
    {
        return $this->keys;
    }

    public function isKeyAllowed(string $key): bool
    {
        if (is_null($this->getKeys())) {
            return true;
        }

        return in_array($key, $this->getKeys());
    }

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

    public function getValue(string $key): mixed
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->normalizeValue($this->data[$key]['value']);
    }

    public function set(
        string $key,
        string $description,
        string|int|float|array|Serializable|null $value): static
    {
        if (!$this->isKeyAllowed($key)) {
            throw new \InvalidArgumentException('Key: "'.$key.'" is not allowed.');
        }

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
        $context->loadFromArray($data);
        return $context;
    }

    public function loadFromArray(array $data): static
    {
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

            $this->set($key, $_data['description'], $_data['value']);
        }

        return $this;
    }

    public function toArray(): array
    {
        $data = $this->data;

        foreach ($data as $key => $_data) {
            $data[$key]['value'] = $this->normalizeValue($_data['value']);
        }

        return $data;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (! str_starts_with($name, 'get') || strlen($name) <= 3) {
            throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
        }

        $suffix = substr($name, 3);
        $returnValueOnly = false;
        if (str_ends_with($suffix, 'Value') && strlen($suffix) > 5) {
            $returnValueOnly = true;
            $suffix = substr($suffix, 0, -5);
        }

        $key = ltrim(strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $suffix)), '_');
        if ($key === '' || ! $this->has($key)) {
            return null;
        }

        return $returnValueOnly ? $this->getValue($key) : $this->get($key);
    }

    protected static function isSerializableValue(mixed $value): bool
    {
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || $value instanceof Serializable) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        return array_all($value, fn($nested) => self::isSerializableValue($nested));

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