<?php

namespace Tests\Feature\ModelListeners\Fileable;

use App\Models\File;
use App\Models\Fileable;
use App\Models\Page;
use App\Models\Snapshot;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteOrphanFileTest extends TestCase
{
    use RefreshDatabase;

    private function makeSnapshot(int $version = 1): Snapshot
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $url = 'https://example.com/page-' . uniqid();
        $page = Page::create([
            'source_id' => $source->id,
            'url' => $url,
            'url_hash' => sha1($url),
        ]);

        return Snapshot::create([
            'page_id' => $page->id,
            'version' => $version,
        ]);
    }

    private function makeFile(): File
    {
        $url = 'https://example.com/' . uniqid() . '.jpg';

        return File::query()->create([
            'url' => $url,
            'url_hash' => sha1($url),
        ]);
    }

    private function attachFileToSnapshot(File $file, Snapshot $snapshot): Fileable
    {
        return Fileable::query()->create([
            'fileable_type' => $snapshot->getMorphClass(),
            'fileable_id' => $snapshot->getKey(),
            'file_id' => $file->id,
        ]);
    }

    public function test_deletes_file_when_last_fileable_is_removed(): void
    {
        $file = $this->makeFile();
        $snapshot = $this->makeSnapshot();
        $fileable = $this->attachFileToSnapshot($file, $snapshot);

        $fileable->delete();

        $this->assertSoftDeleted('files', ['id' => $file->id]);
    }

    public function test_does_not_delete_file_when_other_fileables_still_reference_it(): void
    {
        $file = $this->makeFile();
        $firstSnapshot = $this->makeSnapshot(1);
        $secondSnapshot = $this->makeSnapshot(2);

        $firstFileable = $this->attachFileToSnapshot($file, $firstSnapshot);
        $this->attachFileToSnapshot($file, $secondSnapshot);

        $firstFileable->delete();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'deleted_at' => null,
        ]);
    }
}
