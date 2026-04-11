<?php

namespace App\Contracts\IntentResolver;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

final class IntentKeyword implements Serializable
{
    use SerializableTrait;
    use HasIntent;
    use HasRelevance;

    protected string $keyword = '';

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    /**
     * @throws \InvalidArgumentException When the trimmed keyword is empty.
     */
    public function setKeyword(string $keyword): static
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw new \InvalidArgumentException('Keyword cannot be empty.');
        }

        $this->keyword = $keyword;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{keyword: string, relevance: float|null}
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->getIntent()->toArray(),
            'keyword' => $this->getKeyword(),
            'relevance' => $this->getRelevance(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException When "keyword" is missing or not a non-empty string.
     */
    public static function fromArray(array $data): static
    {
        $instance = new static;

        if (! isset($data['keyword']) || ! is_string($data['keyword'])) {
            throw new \InvalidArgumentException('IntentKeyword requires a non-empty string "keyword".');
        }

        $instance->setIntent(Intent::fromArray($data['intent']));
        $instance->setKeyword($data['keyword']);

        static::parseRelevance($instance, $data);

        return $instance;
    }
}
