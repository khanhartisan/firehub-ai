<?php

namespace App\Contracts\Synthesizer\Critic;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * Editorial feedback on a specific part of an article (reference) with one or more remarks.
 */
final class Criticism implements Serializable
{
    use SerializableTrait;

    /**
     * What this criticism refers to (e.g. DOM element id, outline item id, section label).
     */
    protected ?string $reference = null;

    /**
     * How confident the critic is in this feedback, from 0.00 to 1.00.
     */
    protected ?float $confidence = null;

    /**
     * How important this issue is to address, from 0.00 to 1.00.
     */
    protected ?float $importance = null;

    /**
     * Which critic produced this feedback (e.g. voice, structure, clarity).
     */
    protected ?string $purpose = null;

    /**
     * @var string[]
     */
    protected array $remarks = [];

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): static
    {
        $this->confidence = $confidence !== null ? round($confidence, 2) : null;

        return $this;
    }

    public function getImportance(): ?float
    {
        return $this->importance;
    }

    public function setImportance(?float $importance): static
    {
        $this->importance = $importance !== null ? round($importance, 2) : null;

        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(?string $purpose): static
    {
        $this->purpose = $purpose;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getRemarks(): array
    {
        return $this->remarks;
    }

    /**
     * @param  string[]  $remarks
     */
    public function setRemarks(array $remarks): static
    {
        $this->remarks = array_values(array_map(static fn ($remark) => (string) $remark, $remarks));

        return $this;
    }

    public function addRemark(string $remark): static
    {
        $this->remarks[] = $remark;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->getReference(),
            'confidence' => $this->getConfidence(),
            'importance' => $this->getImportance(),
            'purpose' => $this->getPurpose(),
            'remarks' => $this->getRemarks(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $criticism = new static;

        if (array_key_exists('reference', $data)) {
            $criticism->setReference($data['reference'] !== null ? (string) $data['reference'] : null);
        }

        if (array_key_exists('confidence', $data)) {
            $criticism->setConfidence($data['confidence'] !== null ? (float) $data['confidence'] : null);
        }

        if (array_key_exists('importance', $data)) {
            $criticism->setImportance($data['importance'] !== null ? (float) $data['importance'] : null);
        }

        if (array_key_exists('purpose', $data)) {
            $criticism->setPurpose($data['purpose'] !== null ? (string) $data['purpose'] : null);
        }

        if (isset($data['remarks']) && is_array($data['remarks'])) {
            $criticism->setRemarks($data['remarks']);
        }

        return $criticism;
    }
}
