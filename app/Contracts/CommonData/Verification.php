<?php

namespace App\Contracts\CommonData;

use App\Contracts\Serializable;

final class Verification implements Serializable
{
    use \App\Concerns\Serializable;

    protected ?bool $isValid = null;

    protected ?float $confidence = null;

    /**
     * @var string|null Detailed feedback, especially useful if verification fails.
     */
    protected ?string $reasoning = null;

    public function getIsValid(): ?bool
    {
        return $this->isValid;
    }

    public function setIsValid(?bool $isValid): static
    {
        $this->isValid = $isValid;

        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): static
    {
        $this->confidence = round($confidence, 2);

        return $this;
    }

    public function getReasoning(): ?string
    {
        return $this->reasoning;
    }

    public function setReasoning(?string $reasoning): static
    {
        $this->reasoning = $reasoning;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'is_valid' => $this->getIsValid(),
            'confidence' => $this->getConfidence(),
            'reasoning' => $this->getReasoning(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $verification = new static;

        if (array_key_exists('is_valid', $data)) {
            $verification->setIsValid($data['is_valid'] !== null ? (bool) $data['is_valid'] : null);
        }

        if (array_key_exists('confidence', $data)) {
            $verification->setConfidence($data['confidence'] !== null ? (float) $data['confidence'] : null);
        }

        if (array_key_exists('reasoning', $data)) {
            $verification->setReasoning($data['reasoning'] !== null ? (string) $data['reasoning'] : null);
        }

        return $verification;
    }
}
