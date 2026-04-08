<?php

namespace App\Contracts\SearchEngine;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Full response from a web search: ordered hits plus optional query echo and totals.
 *
 * @implements IteratorAggregate<int, SearchResult>
 */
final readonly class SearchResults implements Countable, IteratorAggregate, Serializable
{
    use SerializableTrait;

    /**
     * @param  list<SearchResult>  $items
     */
    public function __construct(
        public array $items = [],
        public ?string $query = null,
        /** Total hit count when the engine exposes it; otherwise null. */
        public ?int $totalEstimated = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Same shape as {@see self::toArray()}
     */
    public static function fromArray(array $data): static
    {
        $rawItems = $data['items'] ?? [];
        if (! is_array($rawItems)) {
            throw new \InvalidArgumentException('SearchResults: "items" must be a list of result arrays');
        }

        $items = [];
        foreach ($rawItems as $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('SearchResults: each item must be an array');
            }
            $items[] = SearchResult::fromArray($row);
        }

        $query = null;
        if (array_key_exists('query', $data)) {
            $query = is_string($data['query']) ? $data['query'] : null;
        }

        $totalEstimated = null;
        if (array_key_exists('total_estimated', $data) && $data['total_estimated'] !== null) {
            $totalEstimated = (int) $data['total_estimated'];
        }

        return new self(
            items: $items,
            query: $query,
            totalEstimated: $totalEstimated,
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, query: string|null, total_estimated: int|null}
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (SearchResult $r): array => $r->toArray(),
                $this->items
            ),
            'query' => $this->query,
            'total_estimated' => $this->totalEstimated,
        ];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
