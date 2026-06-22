<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ForceDeleteSnapshots;
use App\Models\Page;
use App\Models\Snapshot;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeStatus;
use Tests\TestCase;

class ForceDeleteSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
    }

    private function makeForceDeletableSnapshot(?string $filePath = null): Snapshot
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $url = 'https://example.com/page-' . uniqid();
        $page = Page::create([
            'source_id' => $source->id,
            'url' => $url,
            'url_hash' => sha1($url),
        ]);

        $snapshot = Snapshot::create([
            'page_id' => $page->id,
            'file_path' => $filePath,
            'version' => 1,
        ]);

        $snapshot->delete();

        Snapshot::query()
            ->onlyTrashed()
            ->whereKey($snapshot->id)
            ->update(['cascade_status' => CascadeStatus::DELETED]);

        return Snapshot::query()->onlyTrashed()->findOrFail($snapshot->id);
    }

    public function test_does_nothing_when_no_eligible_snapshots_exist(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $url = 'https://example.com/page-' . uniqid();
        $page = Page::create([
            'source_id' => $source->id,
            'url' => $url,
            'url_hash' => sha1($url),
        ]);

        $snapshot = Snapshot::create([
            'page_id' => $page->id,
            'version' => 1,
        ]);

        $snapshot->delete();

        (new ForceDeleteSnapshots())->handle();

        $this->assertSoftDeleted('snapshots', ['id' => $snapshot->id]);
    }

    public function test_force_deletes_snapshot_and_removes_storage_blob(): void
    {
        $filePath = 'snapshots/' . uniqid() . '.html';
        Storage::put($filePath, '<html>Snapshot content</html>');

        $snapshot = $this->makeForceDeletableSnapshot($filePath);

        (new ForceDeleteSnapshots())->handle();

        $this->assertDatabaseMissing('snapshots', ['id' => $snapshot->id]);
        $this->assertFalse(Storage::exists($filePath));
    }

    public function test_force_deletes_snapshot_when_storage_blob_is_missing(): void
    {
        $filePath = 'snapshots/' . uniqid() . '.html';
        $snapshot = $this->makeForceDeletableSnapshot($filePath);

        (new ForceDeleteSnapshots())->handle();

        $this->assertDatabaseMissing('snapshots', ['id' => $snapshot->id]);
        $this->assertFalse(Storage::exists($filePath));
    }

    public function test_processes_multiple_eligible_snapshots_in_one_run(): void
    {
        $firstPath = 'snapshots/' . uniqid() . '.html';
        $secondPath = 'snapshots/' . uniqid() . '.html';
        Storage::put($firstPath, '<html>one</html>');
        Storage::put($secondPath, '<html>two</html>');

        $firstSnapshot = $this->makeForceDeletableSnapshot($firstPath);
        $secondSnapshot = $this->makeForceDeletableSnapshot($secondPath);

        (new ForceDeleteSnapshots())->handle();

        $this->assertDatabaseMissing('snapshots', ['id' => $firstSnapshot->id]);
        $this->assertDatabaseMissing('snapshots', ['id' => $secondSnapshot->id]);
        $this->assertFalse(Storage::exists($firstPath));
        $this->assertFalse(Storage::exists($secondPath));
    }
}
