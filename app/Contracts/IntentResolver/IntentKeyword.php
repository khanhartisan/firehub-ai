<?php

namespace App\Contracts\IntentResolver;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\CommonData\Concerns\HasRelevance;
use App\Contracts\CommonData\Keyword as KeywordData;
use App\Contracts\Serializable;

final class IntentKeyword implements Serializable
{
    use SerializableTrait;
    use HasIntent;
    use HasRelevance;

    protected KeywordData $keyword;

    public function getKeyword(): KeywordData
    {
        return $this->keyword;
    }

    public function setKeyword(KeywordData $keyword): static
    {
        $this->keyword = $keyword;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{intent: array<string, mixed>, keyword: array{keyword: string, language: string|null, country: string|null}, relevance: float|null}
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->getIntent()->toArray(),
            'keyword' => $this->getKeyword()->toArray(),
            'relevance' => $this->getRelevance(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException When "keyword" is missing or invalid.
     */
    public static function fromArray(array $data): static
    {
        $instance = new static;

        if (! isset($data['keyword'])) {
            throw new \InvalidArgumentException('IntentKeyword requires "keyword".');
        }

        $instance->setIntent(Intent::fromArray($data['intent']));

        if (is_string($data['keyword'])) {
            // Backward compatibility with string keyword payloads.
            $instance->setKeyword(new KeywordData($data['keyword']));
        } elseif (is_array($data['keyword'])) {
            $instance->setKeyword(KeywordData::fromArray($data['keyword']));
        } else {
            throw new \InvalidArgumentException('IntentKeyword "keyword" must be a string or keyword object payload.');
        }

        return $instance->hydrateRelevance($data);
    }
}
