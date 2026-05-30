<?php

namespace App\Contracts\Platforms;

use App\Contracts\Serializable;

class Config implements Serializable
{
    use \App\Concerns\Serializable;

    public function __construct(protected array $config = []) {}

    public function toArray(): array
    {
        return $this->config;
    }

    public static function fromArray(array $data): static
    {
        return new static($data);
    }
}
