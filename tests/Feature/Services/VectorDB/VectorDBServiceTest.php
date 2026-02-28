<?php

namespace Tests\Feature\Services\VectorDB;

use App\Contracts\VectorDB\SearchOptions;
use App\Contracts\VectorDB\Vector;
use App\Contracts\VectorDB\VectorRecord;
use App\Contracts\VectorDB\VectorDB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VectorDBServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_SPACE = 'feature_test_vectors';

    private const VECTOR_DIMENSION = 1536;

    protected function setUp(): void
    {
        try {
            parent::setUp();
            $this->skipUnlessPostgresWithVector();
            $this->cleanTestSpace();
        } catch (\Throwable $e) {
            if ($this->isDatabaseConnectionException($e)) {
                $this->markTestSkipped('PostgreSQL unavailable or vector_records table missing: ' . $e->getMessage());
            }
            throw $e;
        }
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
     * Skip tests when not using PostgreSQL or when vector_records table does not exist (e.g. pgvector not available).
     */
    protected function skipUnlessPostgresWithVector(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('VectorDB feature tests require PostgreSQL.');
        }

        $tableExists = DB::getSchemaBuilder()->hasTable('vector_records');
        if (! $tableExists) {
            $this->markTestSkipped('vector_records table not found. Run migrations with PostgreSQL and pgvector extension.');
        }
    }

    protected function cleanTestSpace(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        if (! DB::getSchemaBuilder()->hasTable('vector_records')) {
            return;
        }
        $this->vectordb()->dropIndex(self::TEST_SPACE);
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
        $record = new VectorRecord(
            id: 'doc-1',
            vector: $this->makeVector(),
            metadata: ['source_id' => 's1', 'type' => 'page']
        );

        $vdb->upsert(self::TEST_SPACE, $record);

        $found = $vdb->get(self::TEST_SPACE, 'doc-1');
        $this->assertNotNull($found);
        $this->assertSame('doc-1', $found->id);
        $this->assertEquals(['source_id' => 's1', 'type' => 'page'], $found->metadata);
        $this->assertCount(self::VECTOR_DIMENSION, $found->vector->toArray());
    }

    public function test_upsert_overwrites_existing_record(): void
    {
        $vdb = $this->vectordb();
        $vdb->upsert(self::TEST_SPACE, new VectorRecord(
            id: 'doc-1',
            vector: $this->makeVector([1.0, 0.0, 0.0]),
            metadata: ['v' => 1]
        ));

        $vdb->upsert(self::TEST_SPACE, new VectorRecord(
            id: 'doc-1',
            vector: $this->makeVector([0.0, 1.0, 0.0]),
            metadata: ['v' => 2]
        ));

        $found = $vdb->get(self::TEST_SPACE, 'doc-1');
        $this->assertNotNull($found);
        $this->assertSame(['v' => 2], $found->metadata);
    }

    public function test_get_returns_null_for_missing_id(): void
    {
        $vdb = $this->vectordb();
        $this->assertNull($vdb->get(self::TEST_SPACE, 'nonexistent'));
    }

    public function test_upsert_many_inserts_multiple_records(): void
    {
        $vdb = $this->vectordb();
        $records = [
            new VectorRecord('batch-1', $this->makeVector(), ['n' => 1]),
            new VectorRecord('batch-2', $this->makeVector(), ['n' => 2]),
            new VectorRecord('batch-3', $this->makeVector(), ['n' => 3]),
        ];

        $vdb->upsertMany(self::TEST_SPACE, $records);

        $this->assertNotNull($vdb->get(self::TEST_SPACE, 'batch-1'));
        $this->assertNotNull($vdb->get(self::TEST_SPACE, 'batch-2'));
        $this->assertNotNull($vdb->get(self::TEST_SPACE, 'batch-3'));
    }

    public function test_delete_removes_record(): void
    {
        $vdb = $this->vectordb();
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('to-delete', $this->makeVector(), []));
        $this->assertNotNull($vdb->get(self::TEST_SPACE, 'to-delete'));

        $vdb->delete(self::TEST_SPACE, 'to-delete');

        $this->assertNull($vdb->get(self::TEST_SPACE, 'to-delete'));
    }

    public function test_delete_by_filter_removes_matching_records(): void
    {
        $vdb = $this->vectordb();
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('a', $this->makeVector(), ['source_id' => 's1']));
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('b', $this->makeVector(), ['source_id' => 's1']));
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('c', $this->makeVector(), ['source_id' => 's2']));

        $vdb->deleteByFilter(self::TEST_SPACE, ['source_id' => 's1']);

        $this->assertNull($vdb->get(self::TEST_SPACE, 'a'));
        $this->assertNull($vdb->get(self::TEST_SPACE, 'b'));
        $this->assertNotNull($vdb->get(self::TEST_SPACE, 'c'));
    }

    public function test_search_returns_nearest_neighbors_by_cosine_similarity(): void
    {
        $vdb = $this->vectordb();
        $queryVec = $this->makeVector();
        $similarVec = $this->makeSimilarVector($queryVec, 0.02);
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('similar-doc', $similarVec, ['title' => 'Similar']));
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('other-doc', $this->makeVector([0.0, 1.0]), ['title' => 'Other']));

        $options = SearchOptions::create(limit: 5);
        $results = $vdb->search(self::TEST_SPACE, $queryVec, $options);

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
            $vdb->upsert(self::TEST_SPACE, new VectorRecord("doc-{$i}", $vec, []));
        }

        $results = $vdb->search(self::TEST_SPACE, $vec, SearchOptions::create(limit: 2));

        $this->assertCount(2, $results);
    }

    public function test_search_respects_metadata_filter(): void
    {
        $vdb = $this->vectordb();
        $vec = $this->makeVector();
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('match', $vec, ['type' => 'article', 'lang' => 'en']));
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('no-match', $vec, ['type' => 'product']));

        $options = SearchOptions::create(limit: 10, metadataFilter: ['type' => 'article']);
        $results = $vdb->search(self::TEST_SPACE, $vec, $options);

        $ids = array_map(fn ($r) => $r->record->id, $results);
        $this->assertContains('match', $ids);
        $this->assertNotContains('no-match', $ids);
    }

    public function test_search_respects_score_threshold(): void
    {
        $vdb = $this->vectordb();
        $vec = $this->makeVector();
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('doc', $vec, []));

        $options = SearchOptions::create(limit: 10, scoreThreshold: 0.9999);
        $results = $vdb->search(self::TEST_SPACE, $vec, $options);

        foreach ($results as $r) {
            $this->assertGreaterThanOrEqual(0.9999, $r->score);
        }
    }

    public function test_drop_index_removes_all_records_in_space(): void
    {
        $vdb = $this->vectordb();
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('x', $this->makeVector(), []));
        $vdb->upsert(self::TEST_SPACE, new VectorRecord('y', $this->makeVector(), []));

        $vdb->dropIndex(self::TEST_SPACE);

        $this->assertNull($vdb->get(self::TEST_SPACE, 'x'));
        $this->assertNull($vdb->get(self::TEST_SPACE, 'y'));
    }

    public function test_spaces_are_isolated(): void
    {
        $vdb = $this->vectordb();
        $spaceA = self::TEST_SPACE.'_a';
        $spaceB = self::TEST_SPACE.'_b';
        $vdb->upsert($spaceA, new VectorRecord('id-1', $this->makeVector(), []));
        $vdb->upsert($spaceB, new VectorRecord('id-1', $this->makeVector(), ['different' => true]));

        $recA = $vdb->get($spaceA, 'id-1');
        $recB = $vdb->get($spaceB, 'id-1');

        $this->assertNotNull($recA);
        $this->assertNotNull($recB);
        $this->assertSame([], $recA->metadata);
        $this->assertSame(['different' => true], $recB->metadata);

        $vdb->dropIndex($spaceA);
        $vdb->dropIndex($spaceB);
    }
}
