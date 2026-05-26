<?php

namespace App\Contracts\Synthesizer\Critic;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * Proposed fixes for a specific part of an article (reference) with one or more adjustments.
 */
final class Rectification implements Serializable
{
    use SerializableTrait;

    /**
     * What this rectification applies to (e.g. DOM element id, outline item id, section label).
     */
    protected ?string $reference = null;

    /**
     * @var string[]
     */
    protected array $adjustments = [];

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAdjustments(): array
    {
        return $this->adjustments;
    }

    /**
     * @param  string[]  $adjustments
     */
    public function setAdjustments(array $adjustments): static
    {
        $this->adjustments = array_values(array_map(static fn ($adjustment) => (string) $adjustment, $adjustments));

        return $this;
    }

    public function addAdjustment(string $adjustment): static
    {
        $this->adjustments[] = $adjustment;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->getReference(),
            'adjustments' => $this->getAdjustments(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $rectification = new static;

        if (array_key_exists('reference', $data)) {
            $rectification->setReference($data['reference'] !== null ? (string) $data['reference'] : null);
        }

        if (isset($data['adjustments']) && is_array($data['adjustments'])) {
            $rectification->setAdjustments($data['adjustments']);
        }

        return $rectification;
    }
}
