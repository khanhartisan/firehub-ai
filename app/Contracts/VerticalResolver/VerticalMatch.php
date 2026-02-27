<?php

namespace App\Contracts\VerticalResolver;

use App\Concerns\Serializable as SerializableConcern;
use App\Contracts\Serializable;

/**
 * A single match from resolving content against a vertical.
 *
 * Holds the matched vertical's identifier and a confidence score (0.0–1.0).
 */
final class VerticalMatch implements Serializable
{
    use SerializableConcern;

    /** Identifier of the matched vertical (e.g. ID or name). */
    protected string $verticalIdentifier;

    /** Confidence score between 0.0 and 1.0. */
    protected float $confidence;

    /**
     * @param  string  $verticalIdentifier  Identifier of the matched vertical.
     * @param  float  $confidence  Confidence score (0.0–1.0).
     */
    public function __construct(string $verticalIdentifier, float $confidence)
    {
        $this->setVerticalIdentifier($verticalIdentifier);
        $this->setConfidence($confidence);
    }

    public function getVerticalIdentifier(): string
    {
        return $this->verticalIdentifier;
    }

    /** @return static */
    public function setVerticalIdentifier(string $verticalIdentifier): static
    {
        $this->verticalIdentifier = $verticalIdentifier;
        return $this;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /** @return static */
    public function setConfidence(float $confidence): static
    {
        $this->confidence = $confidence;
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{vertical_identifier: string, confidence: float}
     */
    public function toArray(): array
    {
        return [
            'vertical_identifier' => $this->getVerticalIdentifier(),
            'confidence' => $this->getConfidence(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array{vertical_identifier?: string, confidence?: float}  $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['vertical_identifier'] ?? throw new \InvalidArgumentException('Invalid vertical data'),
            floatval($data['confidence'] ?? 0.0)
        );
    }
}