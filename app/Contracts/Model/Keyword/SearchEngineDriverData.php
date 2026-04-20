<?php

namespace App\Contracts\Model\Keyword;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\SearchEngine\SearchResults;
use App\Contracts\Serializable;

final class SearchEngineDriverData implements Serializable
{
    use SerializableTrait;

    protected ?SearchResults $searchResults = null;

    /**
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $instance = new static;

        if (array_key_exists('meta', $data) && is_array($data['meta'])) {
            $instance->setMeta($data['meta']);
            unset($data['meta']);
        }

        if (isset($data['search_results']) && is_array($data['search_results'])) {
            $instance->setSearchResults(SearchResults::fromArray($data['search_results']));
            unset($data['search_results']);
            if ($data !== []) {
                $instance->mergeMeta($data);
            }
        }

        if ($instance->getSearchResults() === null && $data !== []) {
            $instance->setMeta($data);
        }

        return $instance;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'search_results' => $this->searchResults?->toArray(),
            'meta' => $this->meta !== [] ? $this->meta : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function getSearchResults(): ?SearchResults
    {
        return $this->searchResults;
    }

    public function setSearchResults(?SearchResults $searchResults): static
    {
        $this->searchResults = $searchResults;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function setMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function mergeMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }
}
