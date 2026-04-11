<?php

namespace App\Contracts\IntentResolver;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * A resolved {@see IntentData} together with the {@see KeywordData} rows that belong to it.
 *
 * Used when mapping many keywords to one or more intents (a keyword may appear under several intents).
 */
final class IntentKeywordsData implements Serializable
{
    use SerializableTrait;

    protected IntentData $intent;

    /** @var list<KeywordData> */
    protected array $keywords = [];

    public function getIntent(): IntentData
    {
        return $this->intent;
    }

    public function setIntent(IntentData $intent): static
    {
        $this->intent = $intent;

        return $this;
    }

    /**
     * @return list<KeywordData>
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param  list<KeywordData>  $keywords
     *
     * @throws \InvalidArgumentException When an element is not a {@see KeywordData} instance.
     */
    public function setKeywords(array $keywords): static
    {
        foreach ($keywords as $index => $keyword) {
            if (! $keyword instanceof KeywordData) {
                throw new \InvalidArgumentException(
                    sprintf('keywords[%s] must be an instance of %s, %s given.', $index, KeywordData::class, get_debug_type($keyword))
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
                static fn (KeywordData $k): array => $k->toArray(),
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
            throw new \InvalidArgumentException('IntentKeywordsData requires an "intent" object.');
        }

        $instance = new static;
        $instance->setIntent(IntentData::fromArray($data['intent']));

        $keywords = [];
        if (isset($data['keywords']) && is_array($data['keywords'])) {
            foreach ($data['keywords'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                try {
                    $keywords[] = KeywordData::fromArray($row);
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
        }

        $instance->setKeywords($keywords);

        return $instance;
    }
}
