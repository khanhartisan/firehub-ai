<?php

namespace App\Contracts\VectorDB;

/**
 * A single result from vector similarity search.
 *
 * Score is typically 0–1 (cosine similarity) or backend-specific; higher = more similar.
 */
final readonly class SearchResult
{
    public function __construct(
        public VectorRecord $record,
        /** Similarity score (0–1 for cosine; interpretation may vary by backend). */
        public float        $score,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            record: VectorRecord::fromArray($data['record']),
            score: (float) $data['score'],
        );
    }

    /**
     * @return array{record: array, score: float}
     */
    public function toArray(): array
    {
        return [
            'record' => $this->record->toArray(),
            'score' => $this->score,
        ];
    }
}
