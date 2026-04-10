<?php

namespace App\Contracts\IntentResolver;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

final class IntentKeywordData implements Serializable
{
    use SerializableTrait;

    protected string $keyword = '';

    protected ?float $relevance = null;

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

    public function getRelevance(): ?float
    {
        return $this->relevance;
    }

    public function setRelevance(?float $relevance): static
    {
        $this->relevance = $relevance;

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
            'keyword' => $this->keyword,
            'relevance' => $this->relevance,
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
            throw new \InvalidArgumentException('IntentKeywordData requires a non-empty string "keyword".');
        }

        $instance->setKeyword($data['keyword']);

        if (array_key_exists('relevance', $data)) {
            $relevance = $data['relevance'];
            if ($relevance === null) {
                $instance->setRelevance(null);
            } elseif (is_int($relevance) || is_float($relevance)) {
                $instance->setRelevance((float) $relevance);
            } elseif (is_string($relevance) && is_numeric($relevance)) {
                $instance->setRelevance((float) $relevance);
            }
        }

        return $instance;
    }
}
