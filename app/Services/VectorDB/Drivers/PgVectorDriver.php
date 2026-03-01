<?php

namespace App\Services\VectorDB\Drivers;

use App\Contracts\VectorDB\SearchOptions;
use App\Contracts\VectorDB\SearchResult;
use App\Contracts\VectorDB\Vector;
use App\Contracts\VectorDB\VectorDB;
use App\Contracts\VectorDB\VectorRecord;
use Illuminate\Support\Facades\DB;
use JsonException;
use Pgvector\Vector as PgVector;

class PgVectorDriver implements VectorDB
{
    /**
     * Column name for the embedding vector (contract uses "vector" per table convention).
     */
    protected const VECTOR_COLUMN = 'vector';

    public function __construct(
        protected array $config = [],
    ) {}

    /**
     * Update vector for an existing row. Model tables are used as vector tables;
     * the record is guaranteed to exist. No metadata column – metadata is used only for WHERE clauses.
     */
    public function upsert(string $index, VectorRecord $record): void
    {
        $table = $this->tableName($index);
        $vec = (string) $this->toPgVector($record->vector);

        $this->query($table)->where('id', $record->id)->update([
            self::VECTOR_COLUMN => $vec,
        ]);
    }

    public function upsertMany(string $index, array $records): void
    {
        foreach ($records as $record) {
            if ($record instanceof VectorRecord) {
                $this->upsert($index, $record);
            }
        }
    }

    /**
     * No-op: record deletion is assumed to be done by the application.
     */
    public function delete(string $index, string $id): void
    {
        // App is responsible for deleting records.
    }

    /**
     * No-op: record deletion by filter is assumed to be done by the application.
     */
    public function deleteByFilter(string $index, array $metadataFilter): void
    {
        // App is responsible for deleting records by filter.
    }

    /**
     * @throws JsonException
     */
    public function get(string $index, string $id): ?VectorRecord
    {
        $row = $this->query($this->tableName($index))->where('id', $id)->first();
        if ($row === null) {
            return null;
        }
        return $this->rowToRecord($row);
    }

    /**
     * @throws JsonException
     */
    public function search(string $index, Vector $queryVector, SearchOptions $options): array
    {
        $table = $this->tableName($index);
        $vecArray = $queryVector->toArray();
        $limit = max(1, min($options->limit, 1000));
        $minSimilarity = $options->scoreThreshold ?? 0.0;

        $q = $this->query($table)
            ->select(['id'])
            ->selectVectorDistance(self::VECTOR_COLUMN, $vecArray, 'distance');

        if ($options->includeVector) {
            $q->addSelect(self::VECTOR_COLUMN.' as embedding');
        } else {
            $q->addSelect(DB::raw('NULL::text as embedding'));
        }

        foreach ($options->metadataFilter ?? [] as $column => $value) {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
            if ($safe !== '') {
                $q->where($safe, $value);
            }
        }

        $q->whereVectorDistanceLessThan(self::VECTOR_COLUMN, $vecArray, 1 - $minSimilarity)
            ->orderByVectorDistance(self::VECTOR_COLUMN, $vecArray)
            ->limit($limit);

        $rows = $q->get();

        $results = [];
        foreach ($rows as $row) {
            $distance = (float) $row->distance;
            $score = 1.0 - $distance;
            if ($options->scoreThreshold !== null && $score < $options->scoreThreshold) {
                continue;
            }
            $record = $this->rowToRecord($row);
            $results[] = new SearchResult($record, $score);
        }
        return $results;
    }

    /**
     * No-op: index/table creation is assumed to be done by the application (e.g. migrations).
     */
    public function ensureIndex(string $index, ?int $dimension = null): void
    {
        // App is responsible for creating vector tables and indexes.
    }

    /**
     * No-op: index/table dropping is assumed to be done by the application.
     */
    public function dropIndex(string $index): void
    {
        // App is responsible for dropping vector tables/indexes.
    }

    protected function query(string $table): \Illuminate\Database\Query\Builder
    {
        return DB::connection($this->connection())->table($table);
    }

    protected function connection(): string
    {
        return $this->config['connection'] ?? 'pgsql';
    }

    /**
     * Resolve table name from contract "index" (table name). Sanitized for SQL safety.
     */
    protected function tableName(string $index): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $index);
        return $safe !== '' ? $safe : 'vector_default';
    }

    protected function defaultDimension(): int
    {
        return (int) ($this->config['default_dimension'] ?? 1536);
    }

    protected function toPgVector(Vector $vector): PgVector
    {
        return new PgVector($vector->toArray());
    }

    protected function rowToRecord(object $row): VectorRecord
    {
        $embeddingValues = [];
        $vectorRaw = $row->embedding ?? $row->vector ?? null;
        if ($vectorRaw !== null) {
            $embeddingValues = is_string($vectorRaw)
                ? json_decode($vectorRaw, true, 512, JSON_THROW_ON_ERROR) ?? []
                : (array) $vectorRaw;
        }
        $metadata = $this->rowToMetadata($row);

        return new VectorRecord(
            id: $row->id,
            vector: new Vector($embeddingValues),
            metadata: $metadata
        );
    }

    /**
     * Extract metadata from row (all columns except id, vector/embedding, score, distance).
     *
     * @return array<string, mixed>
     */
    protected function rowToMetadata(object $row): array
    {
        $metadata = [];
        $skip = ['id', 'embedding', 'vector', 'score', 'distance'];
        foreach ((array) $row as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            $metadata[$key] = $value;
        }
        return $metadata;
    }
}
