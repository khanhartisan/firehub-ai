<?php

namespace Tests\Feature\Services\VectorDB;

use App\Contracts\VectorDB\SearchOptions;
use App\Contracts\VectorDB\Vector;
use App\Contracts\VectorDB\VectorRecord;
use App\Contracts\VectorDB\VectorDB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Pgvector\Vector as PgVector;
use Tests\TestCase;

/**
 * Feature tests for the pgvector VectorDB driver only.
 *
 * Requires PostgreSQL with the pgvector extension. Skips when not available.
 *
 * For this driver, ensureIndex(), dropIndex(), delete(), and deleteByFilter() are no-ops
 * (the application is responsible for table/record lifecycle). These tests assert that
 * no-op behavior. Other VectorDB drivers (e.g. Pinecone, Qdrant) must implement and
 * test those methods to ensure they actually create/drop indexes and delete records.
 */
class PgVectorDriverFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** Table name used as contract "index" (pgvector driver uses index = table name). */
    private const TEST_TABLE = 'feature_test_vectors';

    private const VECTOR_DIMENSION = 1536;

    protected function setUp(): void
    {
        try {
            parent::setUp();
            $this->skipUnlessPostgresWithVector();
            $this->ensureTestTableExists();
        } catch (\Throwable $e) {
            if ($this->isDatabaseConnectionException($e)) {
                $this->markTestSkipped('PostgreSQL unavailable or pgvector extension missing: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Create the test table (driver assumes app handles index/table creation).
     */
    protected function ensureTestTableExists(): void
    {
        $this->createVectorTable(self::TEST_TABLE, self::VECTOR_DIMENSION);
    }

    /**
     * Create a vector table for testing (e.g. for isolated space tests).
     */
    protected function createVectorTable(string $table, int $dimension = self::VECTOR_DIMENSION): void
    {
        \Illuminate\Support\Facades\Schema::connection(DB::getDefaultConnection())->ensureVectorExtensionExists();
        DB::connection()->statement(
            'CREATE TABLE IF NOT EXISTS '.$table.' (id VARCHAR(255) PRIMARY KEY, vector vector('.$dimension.') NOT NULL)'
        );
        DB::connection()->statement(
            "CREATE INDEX IF NOT EXISTS {$table}_vector_hnsw_idx ON {$table} USING hnsw (vector vector_cosine_ops)"
        );
    }

    private function isDatabaseConnectionException(\Throwable $e): bool
    {
        $message = $e->getMessage();
        $connectionErrorPatterns = [
            'could not translate host name',
            'Connection refused',
            'SQLSTATE[08006]',
            'could not connect to server',
            'nodename nor servname provided',
        ];
        foreach ($connectionErrorPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return $e->getPrevious() !== null && $this->isDatabaseConnectionException($e->getPrevious());
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanTestSpace();
        } catch (\Throwable $e) {
            // Ignore connection errors when cleaning up (e.g. when tests were skipped)
        }
        parent::tearDown();
    }

    protected function vectordb(): VectorDB
    {
        return app(VectorDB::class);
    }

    /**
     * Skip tests when not using PostgreSQL (pgvector driver requires PostgreSQL).
     */
    protected function skipUnlessPostgresWithVector(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('VectorDB feature tests require PostgreSQL.');
        }
    }

    protected function cleanTestSpace(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        \Illuminate\Support\Facades\Schema::connection(DB::getDefaultConnection())->dropIfExists(self::TEST_TABLE);
    }

    /**
     * Insert a row so that upsert (update) has something to update. pgvector driver uses
     * model tables and only updates existing rows. No metadata column – use $extra for
     * columns used in WHERE (e.g. source_id, type). Ensure the table has those columns first.
     */
    protected function insertVectorRow(string $table, string $id, ?Vector $vector = null, array $extra = []): void
    {
        $vector = $vector ?? $this->makeVector();
        $vec = (string) new PgVector($vector->toArray());
        if ($extra === []) {
            DB::connection()->statement(
                'INSERT INTO '.$table.' (id, vector) VALUES (?, ?::vector)',
                [$id, $vec]
            );
            return;
        }
        $cols = ['id', 'vector'];
        $placeholders = ['?', '?'];
        $params = [$id, $vec];
        foreach ($extra as $col => $value) {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $col);
            if ($safe !== '') {
                $cols[] = '"'.$safe.'"';
                $placeholders[] = '?';
                $params[] = $value;
            }
        }
        DB::connection()->statement(
            'INSERT INTO '.$table.' ('.implode(', ', $cols).') VALUES ('.implode(', ', $placeholders).')',
            $params
        );
    }

    /**
     * Add columns to the test table so filter tests can use WHERE column = value.
     */
    protected function addFilterColumnsToTestTable(string ...$columns): void
    {
        foreach ($columns as $col) {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            if ($safe !== '') {
                DB::connection()->statement(
                    'ALTER TABLE '.self::TEST_TABLE.' ADD COLUMN IF NOT EXISTS "'.$safe.'" VARCHAR(255)'
                );
            }
        }
    }

    /**
     * Build a vector of the required dimension (e.g. for OpenAI embeddings).
     * If fewer values are given, pads with zeros; if more, truncates.
     */
    protected function makeVector(array $values = []): Vector
    {
        $dim = self::VECTOR_DIMENSION;
        if ($values === []) {
            $floats = array_fill(0, $dim, 0.0);
            $floats[0] = 1.0;

            return new Vector($floats);
        }
        $floats = array_map(fn ($v) => (float) $v, array_slice($values, 0, $dim));
        $floats = array_pad($floats, $dim, 0.0);

        return new Vector($floats);
    }

    /**
     * Two vectors that are identical have cosine similarity 1.0.
     */
    protected function makeSimilarVector(Vector $reference, float $noise = 0.01): Vector
    {
        $arr = $reference->toArray();
        foreach ($arr as $i => $v) {
            $arr[$i] = $v + $noise * (($i % 3) - 1);
        }

        return new Vector($arr);
    }

    public function test_upsert_and_get_roundtrip(): void
    {
        $vdb = $this->vectordb();
        $this->insertVectorRow(self::TEST_TABLE, 'doc-1');
        $record = new VectorRecord(
            id: 'doc-1',
            vector: $this->makeVector(),
            metadata: ['source_id' => 's1', 'type' => 'page']
        );

        $vdb->upsert(self::TEST_TABLE, $record);

        $found = $vdb->get(self::TEST_TABLE, 'doc-1');
        $this->assertNotNull($found);
        $this->assertSame('doc-1', $found->id);
        $this->assertSame([], $found->metadata, 'pgvector driver has no metadata column; metadata is used only for WHERE');
        $this->assertCount(self::VECTOR_DIMENSION, $found->vector->toArray());
    }

    public function test_upsert_overwrites_existing_record(): void
    {
        $vdb = $this->vectordb();
        $this->insertVectorRow(self::TEST_TABLE, 'doc-1');
        $vdb->upsert(self::TEST_TABLE, new VectorRecord(
            id: 'doc-1',
            vector: $this->makeVector([1.0, 0.0, 0.0]),
            metadata: ['v' => 1]
        ));

        $vdb->upsert(self::TEST_TABLE, new VectorRecord(
            id: 'doc-1',
            vector: $this->makeVector([0.0, 1.0, 0.0]),
            metadata: ['v' => 2]
        ));

        $found = $vdb->get(self::TEST_TABLE, 'doc-1');
        $this->assertNotNull($found);
        $this->assertSame([], $found->metadata, 'pgvector driver does not store metadata');
    }

    public function test_get_returns_null_for_missing_id(): void
    {
        $vdb = $this->vectordb();
        $this->assertNull($vdb->get(self::TEST_TABLE, 'nonexistent'));
    }

    public function test_upsert_many_inserts_multiple_records(): void
    {
        $vdb = $this->vectordb();
        $this->insertVectorRow(self::TEST_TABLE, 'batch-1');
        $this->insertVectorRow(self::TEST_TABLE, 'batch-2');
        $this->insertVectorRow(self::TEST_TABLE, 'batch-3');
        $records = [
            new VectorRecord('batch-1', $this->makeVector(), ['n' => 1]),
            new VectorRecord('batch-2', $this->makeVector(), ['n' => 2]),
            new VectorRecord('batch-3', $this->makeVector(), ['n' => 3]),
        ];

        $vdb->upsertMany(self::TEST_TABLE, $records);

        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'batch-1'));
        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'batch-2'));
        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'batch-3'));
    }

    public function test_delete_is_no_op(): void
    {
        $vdb = $this->vectordb();
        $this->insertVectorRow(self::TEST_TABLE, 'to-delete');
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('to-delete', $this->makeVector(), []));
        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'to-delete'));

        $vdb->delete(self::TEST_TABLE, 'to-delete');

        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'to-delete'), 'delete() is a no-op; app is responsible for deleting records');
    }

    public function test_delete_by_filter_is_no_op(): void
    {
        $vdb = $this->vectordb();
        $this->addFilterColumnsToTestTable('source_id');
        $this->insertVectorRow(self::TEST_TABLE, 'a', null, ['source_id' => 's1']);
        $this->insertVectorRow(self::TEST_TABLE, 'b', null, ['source_id' => 's1']);
        $this->insertVectorRow(self::TEST_TABLE, 'c', null, ['source_id' => 's2']);
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('a', $this->makeVector(), []));
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('b', $this->makeVector(), []));
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('c', $this->makeVector(), []));

        $vdb->deleteByFilter(self::TEST_TABLE, ['source_id' => 's1']);

        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'a'), 'deleteByFilter() is a no-op; app is responsible for deleting records');
        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'b'));
        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'c'));
    }

    public function test_search_returns_nearest_neighbors_by_cosine_similarity(): void
    {
        $vdb = $this->vectordb();
        $this->insertVectorRow(self::TEST_TABLE, 'similar-doc');
        $this->insertVectorRow(self::TEST_TABLE, 'other-doc');
        $queryVec = $this->makeVector();
        $similarVec = $this->makeSimilarVector($queryVec, 0.02);
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('similar-doc', $similarVec, ['title' => 'Similar']));
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('other-doc', $this->makeVector([0.0, 1.0]), ['title' => 'Other']));

        $options = SearchOptions::create(limit: 5);
        $results = $vdb->search(self::TEST_TABLE, $queryVec, $options);

        $this->assertNotEmpty($results);
        $ids = array_map(fn ($r) => $r->record->id, $results);
        $this->assertContains('similar-doc', $ids);
        $this->assertGreaterThanOrEqual(0, $results[0]->score);
        $this->assertLessThanOrEqual(1.0, $results[0]->score);
    }

    public function test_search_respects_limit(): void
    {
        $vdb = $this->vectordb();
        $vec = $this->makeVector();
        for ($i = 0; $i < 5; $i++) {
            $this->insertVectorRow(self::TEST_TABLE, "doc-{$i}");
            $vdb->upsert(self::TEST_TABLE, new VectorRecord("doc-{$i}", $vec, []));
        }

        $results = $vdb->search(self::TEST_TABLE, $vec, SearchOptions::create(limit: 2));

        $this->assertCount(2, $results);
    }

    public function test_search_respects_metadata_filter(): void
    {
        $vdb = $this->vectordb();
        $this->addFilterColumnsToTestTable('type');
        $vec = $this->makeVector();
        $this->insertVectorRow(self::TEST_TABLE, 'match', $vec, ['type' => 'article']);
        $this->insertVectorRow(self::TEST_TABLE, 'no-match', $vec, ['type' => 'product']);
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('match', $vec, []));
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('no-match', $vec, []));

        $options = SearchOptions::create(limit: 10, metadataFilter: ['type' => 'article']);
        $results = $vdb->search(self::TEST_TABLE, $vec, $options);

        $ids = array_map(fn ($r) => $r->record->id, $results);
        $this->assertContains('match', $ids);
        $this->assertNotContains('no-match', $ids);
    }

    public function test_search_respects_score_threshold(): void
    {
        $vdb = $this->vectordb();
        $this->insertVectorRow(self::TEST_TABLE, 'doc');
        $vec = $this->makeVector();
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('doc', $vec, []));

        $options = SearchOptions::create(limit: 10, scoreThreshold: 0.9999);
        $results = $vdb->search(self::TEST_TABLE, $vec, $options);

        foreach ($results as $r) {
            $this->assertGreaterThanOrEqual(0.9999, $r->score);
        }
    }

    public function test_drop_index_is_no_op(): void
    {
        $vdb = $this->vectordb();
        $this->insertVectorRow(self::TEST_TABLE, 'x');
        $vdb->upsert(self::TEST_TABLE, new VectorRecord('x', $this->makeVector(), []));

        $vdb->dropIndex(self::TEST_TABLE);

        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::connection(DB::getDefaultConnection())->hasTable(self::TEST_TABLE),
            'dropIndex() is a no-op; app is responsible for dropping tables'
        );
        $this->assertNotNull($vdb->get(self::TEST_TABLE, 'x'));
    }

    public function test_spaces_are_isolated(): void
    {
        $vdb = $this->vectordb();
        $spaceA = self::TEST_TABLE.'_a';
        $spaceB = self::TEST_TABLE.'_b';
        $this->createVectorTable($spaceA);
        $this->createVectorTable($spaceB);
        $this->insertVectorRow($spaceA, 'id-1');
        $this->insertVectorRow($spaceB, 'id-1');
        $vdb->upsert($spaceA, new VectorRecord('id-1', $this->makeVector(), []));
        $vdb->upsert($spaceB, new VectorRecord('id-1', $this->makeVector(), ['different' => true]));

        $recA = $vdb->get($spaceA, 'id-1');
        $recB = $vdb->get($spaceB, 'id-1');

        $this->assertNotNull($recA);
        $this->assertNotNull($recB);
        $this->assertSame('id-1', $recA->id);
        $this->assertSame('id-1', $recB->id);
        $this->assertSame([], $recA->metadata);
        $this->assertSame([], $recB->metadata);

        \Illuminate\Support\Facades\Schema::connection(DB::getDefaultConnection())->dropIfExists($spaceA);
        \Illuminate\Support\Facades\Schema::connection(DB::getDefaultConnection())->dropIfExists($spaceB);
    }
}
