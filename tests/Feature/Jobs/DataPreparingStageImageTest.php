<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ScrapePageJobConcerns\DataPreparingStage;
use App\Models\Page;
use App\Models\Snapshot;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataPreparingStageImageTest extends TestCase
{
    public function test_prepare_data_writes_prepared_image_file(): void
    {
        Storage::fake();

        $entityId = (string) Str::ulid();
        $snapshotId = (string) Str::ulid();

        $entity = new Page([
            'id' => $entityId,
            'url' => 'https://example.com/image',
            'url_hash' => sha1('https://example.com/image'),
        ]);
        $entity->setAttribute('id', $entityId);

        $originalBytes = file_get_contents(base_path('resources/sample-images/sample-big-image.jpg'));
        $this->assertNotFalse($originalBytes);

        $snapshot = new Snapshot([
            'id' => $snapshotId,
            'page_id' => $entity->id,
            'version' => 1,
            'file_extension' => 'jpg',
            'file_path' => 'originals/'.$entity->id.'/sample-big-image.jpg',
            'file_size' => strlen($originalBytes),
            'file_mime_type' => 'image/jpeg',
        ]);
        $snapshot->setAttribute('id', $snapshotId);

        $this->assertTrue(Storage::put($snapshot->file_path, $originalBytes));

        $runner = new class {
            use DataPreparingStage;

            public function run(Page $page): bool
            {
                return $this->handleDataPreparingStage($page);
            }
        };

        $entity->setRelation('currentSnapshot', $snapshot);
        $this->assertTrue($runner->run($entity));

        $preparedPath = 'snapshots/'.$entity->id.'/'.$snapshot->id.'/prepared-image.jpg';
        $this->assertTrue(Storage::exists($preparedPath));

        $preparedBytes = Storage::get($preparedPath);
        $this->assertIsString($preparedBytes);
        $this->assertNotSame('', $preparedBytes);
    }

    public function test_prepare_data_resizes_and_converts_to_jpeg_when_imagick_is_available(): void
    {
        if (!class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick extension is not available in this environment.');
        }

        Storage::fake();

        $entityId = (string) Str::ulid();
        $snapshotId = (string) Str::ulid();

        $entity = new Page([
            'id' => $entityId,
            'url' => 'https://example.com/image',
            'url_hash' => sha1('https://example.com/image'),
        ]);
        $entity->setAttribute('id', $entityId);

        $originalBytes = file_get_contents(base_path('resources/sample-images/sample-big-image.jpg'));
        $this->assertNotFalse($originalBytes);

        $snapshot = new Snapshot([
            'id' => $snapshotId,
            'page_id' => $entity->id,
            'version' => 1,
            'file_extension' => 'jpg',
            'file_path' => 'originals/'.$entity->id.'/sample-big-image.jpg',
            'file_size' => strlen($originalBytes),
            'file_mime_type' => 'image/jpeg',
        ]);
        $snapshot->setAttribute('id', $snapshotId);
        $entity->setRelation('currentSnapshot', $snapshot);

        $this->assertTrue(Storage::put($snapshot->file_path, $originalBytes));

        $runner = new class {
            use DataPreparingStage;

            public function run(Page $page): bool
            {
                return $this->handleDataPreparingStage($page);
            }
        };

        $this->assertTrue($runner->run($entity));

        $preparedPath = 'snapshots/'.$entity->id.'/'.$snapshot->id.'/prepared-image.jpg';
        $this->assertTrue(Storage::exists($preparedPath));

        $preparedBytes = Storage::get($preparedPath);
        $this->assertIsString($preparedBytes);
        $this->assertNotSame('', $preparedBytes);

        $image = new \Imagick();
        $image->readImageBlob($preparedBytes);

        $this->assertSame(1, $image->getNumberImages());
        $this->assertSame('JPEG', strtoupper($image->getImageFormat()));
        $this->assertLessThanOrEqual(1024, $image->getImageWidth());
        $this->assertLessThanOrEqual(1024, $image->getImageHeight());
    }
}

