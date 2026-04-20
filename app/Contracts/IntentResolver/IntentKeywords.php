<?php

namespace App\Contracts\IntentResolver;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * A resolved {@see Intent} together with the {@see IntentKeyword} rows that belong to it.
 *
 * Used when mapping many keywords to one or more intents (a keyword may appear under several intents).
 */
final class IntentKeywords implements Serializable
{
    use SerializableTrait;
    use HasIntent;

    /** @var list<IntentKeyword> */
    protected array $intentKeywords = [];

    /**
     * @return list<IntentKeyword>
     */
    public function getIntentKeywords(): array
    {
        return $this->intentKeywords;
    }

    /**
     * @param  list<IntentKeyword>  $intentKeywords
     *
     * @throws \InvalidArgumentException When an element is not a {@see IntentKeyword} instance.
     */
    public function setIntentKeywords(array $intentKeywords): static
    {
        $this->intentKeywords = [];
        foreach ($intentKeywords as $index => $intentKeyword) {
            if (! $intentKeyword instanceof IntentKeyword) {
                throw new \InvalidArgumentException(
                    sprintf('intentKeywords[%s] must be an instance of %s, %s given.', $index, IntentKeyword::class, get_debug_type($intentKeyword))
                );
            }

            $this->addIntentKeyword($intentKeyword);
        }

        return $this;
    }

    public function addIntentKeyword(IntentKeyword $intentKeyword): static
    {
        $incomingKeyword = $intentKeyword->getKeyword()->toArray();
        foreach ($this->intentKeywords as $index => $existingKeyword) {
            if ($existingKeyword->getKeyword()->toArray() === $incomingKeyword) {
                $this->intentKeywords[$index] = $intentKeyword;

                return $this;
            }
        }

        $this->intentKeywords[] = $intentKeyword;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{intent: array<string, mixed>, intent_keywords: list<array{keyword: array{keyword: string, language: string|null, country: string|null}, relevance: float|null}>}
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent->toArray(),
            'intent_keywords' => array_map(
                function (IntentKeyword $k): array {
                    $data = $k->toArray();
                    unset($data['intent']);
                    return $data;
                },
                $this->intentKeywords,
            ),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        if (! isset($data['intent']) || ! is_array($data['intent'])) {
            throw new \InvalidArgumentException('IntentKeywords requires an "intent" object.');
        }

        $instance = new static;
        $instance->setIntent(Intent::fromArray($data['intent']));

        $intentKeywords = [];
        $rows = null;
        if (isset($data['intent_keywords']) && is_array($data['intent_keywords'])) {
            $rows = $data['intent_keywords'];
        } elseif (isset($data['keywords']) && is_array($data['keywords'])) {
            // Backward compatibility for older payloads.
            $rows = $data['keywords'];
        }

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                try {
                    $row['intent'] = $data['intent'];
                    $intentKeywords[] = IntentKeyword::fromArray($row);
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
        }

        $instance->setIntentKeywords($intentKeywords);

        return $instance;
    }
}
