<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ForceDeleteFiles;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeStatus;
use Tests\TestCase;

class ForceDeleteFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
    }

    private function makeForceDeletableFile(?string $path = null): File
    {
        $url = 'https://example.com/' . uniqid() . '.jpg';

        $file = File::query()->create([
            'url' => $url,
            'url_hash' => sha1($url),
            'path' => $path,
        ]);

        $file->delete();

        File::query()
            ->onlyTrashed()
            ->whereKey($file->id)
            ->update(['cascade_status' => CascadeStatus::DELETED]);

        return File::query()->onlyTrashed()->findOrFail($file->id);
    }

    public function test_does_nothing_when_no_eligible_files_exist(): void
    {
        $url = 'https://example.com/' . uniqid() . '.jpg';

        $file = File::query()->create([
            'url' => $url,
            'url_hash' => sha1($url),
        ]);

        $file->delete();

        (new ForceDeleteFiles())->handle();

        $this->assertSoftDeleted('files', ['id' => $file->id]);
    }

    public function test_force_deletes_file_and_removes_storage_blob(): void
    {
        $path = 'files/' . uniqid() . '.jpg';
        Storage::put($path, 'file contents');

        $file = $this->makeForceDeletableFile($path);

        (new ForceDeleteFiles())->handle();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertFalse(Storage::exists($path));
    }

    public function test_force_deletes_file_when_storage_blob_is_missing(): void
    {
        $path = 'files/' . uniqid() . '.jpg';
        $file = $this->makeForceDeletableFile($path);

        (new ForceDeleteFiles())->handle();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertFalse(Storage::exists($path));
    }

    public function test_processes_multiple_eligible_files_in_one_run(): void
    {
        $pathOne = 'files/' . uniqid() . '.jpg';
        $pathTwo = 'files/' . uniqid() . '.jpg';
        Storage::put($pathOne, 'one');
        Storage::put($pathTwo, 'two');

        $firstFile = $this->makeForceDeletableFile($pathOne);
        $secondFile = $this->makeForceDeletableFile($pathTwo);

        (new ForceDeleteFiles())->handle();

        $this->assertDatabaseMissing('files', ['id' => $firstFile->id]);
        $this->assertDatabaseMissing('files', ['id' => $secondFile->id]);
        $this->assertFalse(Storage::exists($pathOne));
        $this->assertFalse(Storage::exists($pathTwo));
    }
}
