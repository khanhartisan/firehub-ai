<?php

namespace App\Contracts\Synthesizer\IdeaForge;

use App\Contracts\Serializable;
use App\Enums\IntentType;

final class IntentTypeSuggestion implements Serializable
{
    use \App\Concerns\Serializable;

    protected IntentType $intentType;

    protected ?float $confidence = null;

    protected ?string $reason = null;

    public function __construct(IntentType $intentType, ?float $confidence = null, ?string $reason = null)
    {
        $this->intentType = $intentType;
        $this->confidence = $confidence;
        $this->reason = $reason;
    }

    public function getIntentType(): IntentType
    {
        return $this->intentType;
    }

    public function setIntentType(IntentType $intentType): static
    {
        $this->intentType = $intentType;

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
            'intent_type' => $this->getIntentType()?->value,
            'confidence' => $this->getConfidence(),
            'reason' => $this->getReason(),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (! array_key_exists('intent_type', $data)) {
            throw new \Exception('intent_type must be set');
        }

        $raw = $data['intent_type'];
        $intentType = $raw instanceof IntentType ? $raw : (is_string($raw) ? IntentType::tryFrom($raw) : null);
        if (! $intentType instanceof IntentType) {
            throw new \Exception('intent_type is invalid');
        }

        $suggestion = new static($intentType);

        if (array_key_exists('confidence', $data)) {
            $suggestion->setConfidence($data['confidence'] !== null ? (float) $data['confidence'] : null);
        }

        if (array_key_exists('reason', $data)) {
            $suggestion->setReason($data['reason'] !== null ? (string) $data['reason'] : null);
        }

        return $suggestion;
    }
}