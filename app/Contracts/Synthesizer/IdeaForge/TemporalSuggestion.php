<?php

namespace App\Contracts\Synthesizer\IdeaForge;

use App\Contracts\Serializable;
use App\Enums\Temporal;

final class TemporalSuggestion implements Serializable
{
    use \App\Concerns\Serializable;

    protected Temporal $temporal;

    protected ?float $confidence = null;

    protected ?string $reason = null;

    public function __construct(Temporal $temporal, ?float $confidence = null, ?string $reason = null)
    {
        $this->temporal = $temporal;
        $this->confidence = $confidence;
        $this->reason = $reason;
    }

    public function getTemporal(): Temporal
    {
        return $this->temporal;
    }

    public function setTemporal(Temporal $temporal): static
    {
        $this->temporal = $temporal;

        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): static
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'temporal' => $this->getTemporal()?->value,
            'confidence' => $this->getConfidence(),
            'reason' => $this->getReason(),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (! array_key_exists('temporal', $data)) {
            throw new \Exception('temporal must be set');
        }

        $raw = $data['temporal'];
        $temporal = $raw instanceof Temporal ? $raw : (is_string($raw) ? Temporal::tryFrom($raw) : null);
        if (! $temporal instanceof Temporal) {
            throw new \Exception('temporal is invalid');
        }

        $suggestion = new static($temporal);

        if (array_key_exists('confidence', $data)) {
            $suggestion->setConfidence($data['confidence'] !== null ? (float) $data['confidence'] : null);
        }

        if (array_key_exists('reason', $data)) {
            $suggestion->setReason($data['reason'] !== null ? (string) $data['reason'] : null);
        }

        return $suggestion;
    }
}