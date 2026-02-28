<?php

namespace App\Contracts\VectorDB;

/**
 * Options for vector similarity search.
 */
final readonly class SearchOptions
{
    public function __construct(
        /** Maximum number of results to return. */
        public int    $limit = 10,
        /** Optional metadata filter (e.g. ['source_id' => 'abc', 'type' => 'page']). */
        public array  $metadataFilter = [],
        /** Minimum similarity score (0–1). Results below this are excluded. */
        public ?float $scoreThreshold = null,
        /** Include the full vector in results. Default false to reduce payload. */
        public bool   $includeVector = false,
    ) {}

    public static function create(
        int $limit = 10,
        array $metadataFilter = [],
        ?float $scoreThreshold = null,
        bool $includeVector = false,
    ): self {
        return new self(
            limit: $limit,
            metadataFilter: $metadataFilter,
            scoreThreshold: $scoreThreshold,
            includeVector: $includeVector,
        );
    }
}
