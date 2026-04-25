<?php

namespace App\Contracts\CommonData\Concerns;

trait HasMeta
{
    protected array $meta = [];

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function addMeta(string $key, string|int|float|null $value): static
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function setMeta(array $meta): static
    {
        $this->meta = [];
        foreach ($meta as $key => $value) {
            $this->addMeta($key, $value);
        }
        return $this;
    }

    public function hasMeta(string $key): bool
    {
        return array_key_exists($key, $this->meta);
    }

    public function removeMeta(string $key): static
    {
        if ($this->hasMeta($key)) {
            unset($this->meta[$key]);
        }
        return $this;
    }

    public function hydrateMeta(array $data): static
    {
        if (isset($data['meta']) and is_array($data['meta'])) {
            $this->setMeta($data['meta']);
        }

        return $this;
    }
}