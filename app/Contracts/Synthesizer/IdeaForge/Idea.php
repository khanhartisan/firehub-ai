<?php

namespace App\Contracts\Synthesizer\IdeaForge;

use App\Contracts\Identifiable;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Serializable;
use Illuminate\Support\Str;

final class Idea implements Identifiable, Serializable
{
    use \App\Concerns\Identifiable;
    use \App\Concerns\Serializable;

    protected Intent $intent;

    protected ?float $confidence = null;

    protected ?string $reason = null;

    public function __construct(Intent $intent, ?float $confidence = null, ?string $reason = null)
    {
        $this->intent = $intent;
        $this->confidence = $confidence;
        $this->reason = $reason;

        $this->setIdentifier(Str::uuid()->toString());
    }

    public function getIntent(): Intent
    {
        return $this->intent;
    }

    public function setIntent(Intent $intent): static
    {
        $this->intent = $intent;

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
            'identifier' => $this->getIdentifier(),
            'intent' => $this->getIntent()->toArray(),
            'confidence' => $this->getConfidence(),
            'reason' => $this->getReason(),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (! array_key_exists('intent', $data)) {
            throw new \Exception('intent must be set');
        }

        $rawIntent = $data['intent'];
        $intent = null;
        if ($rawIntent instanceof Intent) {
            $intent = $rawIntent;
        } elseif (is_array($rawIntent)) {
            $intent = Intent::fromArray($rawIntent);
        }

        if (! $intent instanceof Intent) {
            throw new \Exception('intent is invalid');
        }

        $idea = new static($intent);

        if (array_key_exists('confidence', $data)) {
            $idea->setConfidence($data['confidence'] !== null ? (float) $data['confidence'] : null);
        }

        if (array_key_exists('reason', $data)) {
            $idea->setReason($data['reason'] !== null ? (string) $data['reason'] : null);
        }

        if (array_key_exists('identifier', $data)) {
            $idea->setIdentifier($data['identifier'] !== null ? (string) $data['identifier'] : null);
        }

        return $idea;
    }
}