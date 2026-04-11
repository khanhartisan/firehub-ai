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
    protected array $keywords = [];

    /**
     * @return list<IntentKeyword>
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param  list<IntentKeyword>  $keywords
     *
     * @throws \InvalidArgumentException When an element is not a {@see IntentKeyword} instance.
     */
    public function setKeywords(array $keywords): static
    {
        foreach ($keywords as $index => $keyword) {
            if (! $keyword instanceof IntentKeyword) {
                throw new \InvalidArgumentException(
                    sprintf('keywords[%s] must be an instance of %s, %s given.', $index, IntentKeyword::class, get_debug_type($keyword))
                );
            }
        }

        $this->keywords = array_values($keywords);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{intent: array<string, mixed>, keywords: list<array{keyword: string, relevance: float|null}>}
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent->toArray(),
            'keywords' => array_map(
                function (IntentKeyword $k): array {
                    $data = $k->toArray();
                    unset($data['intent']);
                    return $data;
                },
                $this->keywords,
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

        $keywords = [];
        if (isset($data['keywords']) && is_array($data['keywords'])) {
            foreach ($data['keywords'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                try {
                    $row['intent'] = $data['intent'];
                    $keywords[] = IntentKeyword::fromArray($row);
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
        }

        $instance->setKeywords($keywords);

        return $instance;
    }
}
