<?php

namespace App\Contracts\VerticalResolver;

use App\Concerns\Serializable as SerializableConcern;
use App\Contracts\Serializable;

final class VerticalMatch implements Serializable
{
    use SerializableConcern;

    public function __construct(protected string $vertical, protected float $confidence)
    {
    }

    public function getVertical(): string
    {
        return $this->vertical;
    }

    public function setVertical(string $vertical): static
    {
        $this->vertical = $vertical;
        return $this;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function setConfidence(float $confidence): static
    {
        $this->confidence = $confidence;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'vertical' => $this->getVertical(),
            'confidence' => $this->getConfidence(),
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['vertical'] ?? throw new \InvalidArgumentException('Invalid vertical data'),
            floatval($data['confidence'] ?? 0.0)
        );
    }
}