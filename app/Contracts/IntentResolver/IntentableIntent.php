<?php

namespace App\Contracts\IntentResolver;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\CommonData\Concerns\HasRelevance;
use App\Contracts\Serializable;

/**
 * Links an {@see Intent} to an {@see Intentable} (e.g. content being classified) with optional relevance.
 */
final class IntentableIntent implements Serializable
{
    use HasIntent;
    use HasRelevance;
    use SerializableTrait;

    protected ?Intentable $intentable = null;

    public function getIntentable(): ?Intentable
    {
        return $this->intentable;
    }

    public function setIntentable(?Intentable $intentable): static
    {
        $this->intentable = $intentable;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{intent: array<string, mixed>, intentable: array<string, mixed>|null, relevance: float|null}
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent->toArray(),
            'intentable' => $this->intentable?->toArray(),
            'relevance' => $this->relevance,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException When "intent" is missing or invalid.
     */
    public static function fromArray(array $data): static
    {
        $instance = new static;

        if (! isset($data['intent']) || ! is_array($data['intent'])) {
            throw new \InvalidArgumentException('IntentableIntent requires an "intent" object.');
        }

        $instance->setIntent(Intent::fromArray($data['intent']));

        if (isset($data['intentable']) && is_array($data['intentable'])) {
            $instance->setIntentable(Intentable::fromArray($data['intentable']));
        }

        return $instance->hydrateRelevance($data);
    }
}
