<?php

namespace App\Contracts\VectorDB;

/**
 * Abstracts vector database interactions for semantic search and similarity lookups.
 *
 * Implementations may use Pinecone, Qdrant, Upstash, pgvector, or other backends.
 * Used for RAG, finding similar entities/snapshots, deduplication, and content search.
 */
interface VectorDB
{
    /**
     * Upsert a single vector record (insert or update by ID).
     *
     * @param  string  $index  Index/collection name (e.g. "entities", "snapshots")
     * @param  VectorRecord  $record  The record to upsert (id, vector, metadata)
     */
    public function upsert(string $index, VectorRecord $record): void;

    /**
     * Upsert multiple vector records in batch.
     *
     * @param  string  $index  Index/collection name
     * @param  VectorRecord[]  $records  Records to upsert
     */
    public function upsertMany(string $index, array $records): void;

    /**
     * Delete a record by ID.
     *
     * @param  string  $index  Index/collection name
     * @param  string  $id  Record identifier
     */
    public function delete(string $index, string $id): void;

    /**
     * Delete records matching a metadata filter.
     *
     * @param  string  $index  Index/collection name
     * @param  array<string, mixed>  $metadataFilter  Key-value filter (e.g. ['source_id' => 'abc'])
     */
    public function deleteByFilter(string $index, array $metadataFilter): void;

    /**
     * Retrieve a single record by ID.
     *
     * @param  string  $index  Index/collection name
     * @param  string  $id  Record identifier
     * @return VectorRecord|null  The record or null if not found
     */
    public function get(string $index, string $id): ?VectorRecord;

    /**
     * Search for similar vectors (nearest neighbors).
     *
     * @param  string  $index  Index/collection name
     * @param  Vector  $queryVector  Query embedding
     * @param  SearchOptions  $options  Limit, metadata filter, score threshold, etc.
     * @return SearchResult[]  Results ordered by similarity (highest first)
     */
    public function search(string $index, Vector $queryVector, SearchOptions $options): array;

    /**
     * Ensure an index exists, optionally with given dimension.
     *
     * @param  string  $index  Index/collection name
     * @param  int|null  $dimension  Vector dimension (required for creation; null = no-op if exists)
     */
    public function ensureIndex(string $index, ?int $dimension = null): void;

    /**
     * Drop an index and all its data.
     *
     * @param  string  $index  Index/collection name
     */
    public function dropIndex(string $index): void;
}
