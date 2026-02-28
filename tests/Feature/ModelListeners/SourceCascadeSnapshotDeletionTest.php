<?php

namespace Tests\Feature\ModelListeners;

use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KhanhArtisan\LaravelBackbone\RelationCascade\Jobs\CascadeDelete;
use Tests\TestCase;

class SourceCascadeSnapshotDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    public function test_deleting_source_cascades_to_snapshots_and_deletes_snapshot_files(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page-' . uniqid(),
            'url_hash' => sha1('https://example.com/page-' . uniqid()),
        ]);

        $filePath = 'snapshots/' . $entity->id . '/' . Str::ulid() . '.html';
        Storage::put($filePath, '<html>Snapshot content</html>');

        $snapshot = Snapshot::create([
            'entity_id' => $entity->id,
            'file_path' => $filePath,
            'version' => 1,
        ]);

        $this->assertDatabaseHas('snapshots', ['id' => $snapshot->id]);
        $this->assertTrue(Storage::exists($filePath));

        $source->delete();

        // Run cascade delete job until completion (Source -> Entity -> Snapshot chain)
        for ($i = 0; $i < 5; $i++) {
            CascadeDelete::dispatch();
        }

        $this->assertDatabaseMissing('snapshots', ['id' => $snapshot->id]);
        $this->assertFalse(Storage::exists($filePath));
    }
}
