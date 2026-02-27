<?php

namespace App\Contracts\VerticalResolver;

use App\Concerns\Serializable as SerializableConcern;
use App\Contracts\Serializable;

final class VerticalMatch implements Serializable
{
    use SerializableConcern;

    protected string $verticalIdentifier;

    protected float $confidence;

    public function __construct(string $verticalIdentifier, float $confidence)
    {
        $this->setVerticalIdentifier($verticalIdentifier);
        $this->setConfidence($confidence);
    }

    public function getVerticalIdentifier(): string
    {
        return $this->verticalIdentifier;
    }

    public function setVerticalIdentifier(string $verticalIdentifier): static
    {
        $this->verticalIdentifier = $verticalIdentifier;
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
            'vertical_identifier' => $this->getVerticalIdentifier(),
            'confidence' => $this->getConfidence(),
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            $data['vertical_identifier'] ?? throw new \InvalidArgumentException('Invalid vertical data'),
            floatval($data['confidence'] ?? 0.0)
        );
    }
}