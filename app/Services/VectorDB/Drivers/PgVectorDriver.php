<?php

namespace App\Services\VectorDB\Drivers;

use App\Contracts\VectorDB\SearchOptions;
use App\Contracts\VectorDB\SearchResult;
use App\Contracts\VectorDB\Vector;
use App\Contracts\VectorDB\VectorDB;
use App\Contracts\VectorDB\VectorRecord;
use Illuminate\Support\Facades\DB;
use Pgvector\Vector as PgVector;

class PgVectorDriver implements VectorDB
{
    /**
     * Column name for the space (contract "index" = collection/space name).
     */
    protected const INDEX_COLUMN = 'space';

    public function __construct(
        protected array $config = [],
    ) {}

    public function upsert(string $index, VectorRecord $record): void
    {
        $table = $this->tableName();
        $embedding = $this->toPgVector($record->vector);
        $metadata = json_encode($record->metadata);

        DB::connection($this->connection())->statement(
            'INSERT INTO '.$table.' ('.self::INDEX_COLUMN.', id, embedding, metadata) VALUES (?, ?, ?::vector, ?::jsonb)
             ON CONFLICT ('.self::INDEX_COLUMN.', id) DO UPDATE SET embedding = EXCLUDED.embedding, metadata = EXCLUDED.metadata',
            [$index, $record->id, (string) $embedding, $metadata]
        );
    }

    public function upsertMany(string $index, array $records): void
    {
        foreach ($records as $record) {
            if ($record instanceof VectorRecord) {
                $this->upsert($index, $record);
            }
        }
    }

    public function delete(string $index, string $id): void
    {
        $table = $this->tableName();

        DB::connection($this->connection())->statement(
            'DELETE FROM '.$table.' WHERE '.self::INDEX_COLUMN.' = ? AND id = ?',
            [$index, $id]
        );
    }

    public function deleteByFilter(string $index, array $metadataFilter): void
    {
        if (empty($metadataFilter)) {
            return;
        }

        $table = $this->tableName();
        $filterJson = json_encode($metadataFilter);

        DB::connection($this->connection())->statement(
            'DELETE FROM '.$table.' WHERE '.self::INDEX_COLUMN.' = ? AND metadata @> ?::jsonb',
            [$index, $filterJson]
        );
    }

    public function get(string $index, string $id): ?VectorRecord
    {
        $table = $this->tableName();

        $row = DB::connection($this->connection())
            ->selectOne(
                'SELECT id, embedding::text as embedding, metadata FROM '.$table.' WHERE '.self::INDEX_COLUMN.' = ? AND id = ?',
                [$index, $id]
            );

        if (! $row) {
            return null;
        }

        return $this->rowToRecord($row);
    }

    public function search(string $index, Vector $queryVector, SearchOptions $options): array
    {
        $table = $this->tableName();
        $embedding = (string) $this->toPgVector($queryVector);
        $limit = max(1, min($options->limit, 1000));

        $wheres = [self::INDEX_COLUMN.' = ?'];
        $params = [$embedding, $index];

        if (! empty($options->metadataFilter)) {
            $wheres[] = 'metadata @> ?::jsonb';
            $params[] = json_encode($options->metadataFilter);
        }

        $whereSql = ' WHERE ' . implode(' AND ', $wheres);
        $params[] = $embedding;
        $params[] = $limit;

        $embeddingSelect = $options->includeVector ? 'embedding::text as embedding' : 'NULL::text as embedding';

        $sql = "SELECT id, {$embeddingSelect}, metadata,
                       (1 - (embedding <=> ?::vector)) as score
                FROM {$table}
                {$whereSql}
                ORDER BY embedding <=> ?::vector
                LIMIT ?";

        $rows = DB::connection($this->connection())->select($sql, $params);

        $results = [];
        foreach ($rows as $row) {
            $score = (float) $row->score;
            if ($options->scoreThreshold !== null && $score < $options->scoreThreshold) {
                continue;
            }
            $record = $this->rowToRecord($row);
            $results[] = new SearchResult($record, $score);
        }

        return $results;
    }

    public function ensureIndex(string $index, ?int $dimension = null): void
    {
        // We have this covered in the create_vector_records_table migration file
        return;
    }

    public function dropIndex(string $index): void
    {
        $table = $this->tableName();
        DB::connection($this->connection())->statement(
            'DELETE FROM '.$table.' WHERE '.self::INDEX_COLUMN.' = ?',
            [$index]
        );
    }

    protected function connection(): string
    {
        return $this->config['connection'] ?? 'pgsql';
    }

    protected function tableName(): string
    {
        return $this->config['table'] ?? 'vector_records';
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
        if ($row->embedding !== null) {
            $embeddingValues = is_string($row->embedding)
                ? json_decode($row->embedding, true, 512, JSON_THROW_ON_ERROR) ?? []
                : (array) $row->embedding;
        }
        $metadata = is_string($row->metadata)
            ? json_decode($row->metadata, true, 512, JSON_THROW_ON_ERROR) ?? []
            : (array) $row->metadata;

        return new VectorRecord(
            id: $row->id,
            vector: new Vector($embeddingValues),
            metadata: $metadata
        );
    }
}
