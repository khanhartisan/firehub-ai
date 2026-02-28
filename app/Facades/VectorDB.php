<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\VectorDB\VectorDB driver(string|null $driver = null)
 * @method static void upsert(string $index, \App\Contracts\VectorDB\VectorRecord $record)
 * @method static void upsertMany(string $index, array $records)
 * @method static void delete(string $index, string $id)
 * @method static void deleteByFilter(string $index, array $metadataFilter)
 * @method static \App\Contracts\VectorDB\VectorRecord|null get(string $index, string $id)
 * @method static \App\Contracts\VectorDB\SearchResult[] search(string $index, \App\Contracts\VectorDB\Vector $queryVector, \App\Contracts\VectorDB\SearchOptions $options)
 * @method static void ensureIndex(string $index, int|null $dimension = null)
 * @method static void dropIndex(string $index)
 *
 * @see \App\Services\VectorDB\VectorDBManager
 */
class VectorDB extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'vectordb.manager';
    }
}
