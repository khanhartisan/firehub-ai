<?php

namespace App\Contracts\CommonData;

use App\Contracts\CommonData\Concerns\HasVerification;
use App\Contracts\FactChecker\FactCheckable;
use App\Contracts\Serializable;

final class Fact implements FactCheckable, Serializable
{
    use HasVerification;
    use \App\Concerns\Serializable;

    protected readonly string $fact;

    public function __construct(string $fact)
    {
        $fact = trim($fact);
        if ($fact === '') {
            throw new \InvalidArgumentException('Fact cannot be empty.');
        }

        $this->fact = $fact;
    }

    public function getFact(): string
    {
        return $this->fact;
    }

    public function getFactClaim(): string
    {
        return $this->getFact();
    }

    public function toArray(): array
    {
        return [
            'fact' => $this->getFact(),
            'verification' => $this->getVerification()?->toArray(),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (! isset($data['fact']) || ! is_string($data['fact'])) {
            throw new \InvalidArgumentException('Fact requires a string "fact".');
        }

        return new static($data['fact'])
            ->hydrateVerification($data);
    }
}