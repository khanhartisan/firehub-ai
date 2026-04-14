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
        $suggestion = new static; // TODO: Missing required param

        if (array_key_exists('temporal', $data)) {
            $raw = $data['temporal'];
            if ($raw === null || $raw === '') {
                $suggestion->setTemporal(null); // TODO: Null is now allowed
            } else {
                $suggestion->setTemporal($raw instanceof Temporal ? $raw : Temporal::tryFrom((string) $raw));
            }
        } else {
            throw new \Exception('temporal must be set');
        }

        if (array_key_exists('confidence', $data)) {
            $suggestion->setConfidence($data['confidence'] !== null ? (float) $data['confidence'] : null);
        }

        if (array_key_exists('reason', $data)) {
            $suggestion->setReason($data['reason'] !== null ? (string) $data['reason'] : null);
        }

        return $suggestion;
    }
}