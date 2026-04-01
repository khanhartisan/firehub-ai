<?php

namespace Tests\Feature\Jobs;

use App\Contracts\FileVision\FileInformation;
use App\Contracts\VectorDB\Vector;
use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use App\Facades\FileVision;
use App\Facades\Scraper;
use App\Facades\TextEmbedding;
use App\Jobs\ScrapeFileJob;
use App\Jobs\ScrapeFileJobDispatcher;
use App\Models\File;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Mockery;
use Tests\TestCase;

class ScrapeFileJobTest extends TestCase
{
    use RefreshDatabase;

    /** Minimal valid JPEG (1×1 px) for fetch / prepare tests. */
    private static function jpegBytes(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==',
            true
        ) ?: '';
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.max_scrape_attempts', 5);
        Storage::fake(config('filesystems.default'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeFile(array $overrides = []): File
    {
        $url = $overrides['url'] ?? 'https://example.com/photo.jpg';

        return File::query()->create(array_merge([
            'url' => $url,
            'url_hash' => sha1($url),
            'scraping_status' => ScrapingStatus::QUEUED,
        ], $overrides));
    }

    public function test_fetch_skips_when_status_is_not_queued(): void
    {
        $file = $this->makeFile(['scraping_status' => ScrapingStatus::PENDING]);

        Scraper::shouldReceive('fetch')->never();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame(ScrapingStatus::PENDING, $file->scraping_status);
        Storage::assertMissing('files/'.$file->id.'/data.bin');
    }

    public function test_fetch_http_4xx_increments_attempts_and_sets_failed_status(): void
    {
        $file = $this->makeFile(['attempts' => 0]);

        Scraper::shouldReceive('fetch')
            ->once()
            ->andReturn(new Response(404, ['Content-Type' => 'text/plain'], 'Not Found'));

        Queue::fake();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame(1, $file->attempts);
        $this->assertSame(ScrapingStatus::FAILED, $file->scraping_status);
        Queue::assertNothingPushed();
    }

    public function test_fetch_success_stores_blob_and_advances_stage(): void
    {
        $bytes = self::jpegBytes();
        $this->assertNotSame('', $bytes);

        Scraper::shouldReceive('fetch')
            ->once()
            ->andReturn(new Response(200, ['Content-Type' => 'image/jpeg'], $bytes));

        $file = $this->makeFile();

        Queue::fake();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame(ScrapingStatus::PROCESSING, $file->scraping_status);
        $this->assertSame(ScrapingStage::DATA_PREPARING, $file->scraping_stage);
        $this->assertSame('jpg', $file->extension);
        Storage::assertExists('files/'.$file->id.'/data.bin');
        Queue::assertPushed(ScrapeFileJobDispatcher::class, 1);
    }

    public function test_data_prepare_marks_failed_for_non_image_extension(): void
    {
        $file = $this->makeFile([
            'scraping_status' => ScrapingStatus::PROCESSING,
            'scraping_stage' => ScrapingStage::DATA_PREPARING,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
        ]);
        $file->path = 'files/'.$file->id.'/data.bin';
        $file->save();

        Storage::put($file->path, '%PDF-1.4 fake');

        Queue::fake();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame(ScrapingStatus::FAILED, $file->scraping_status);
        $this->assertNull($file->scraping_stage);
        Queue::assertNothingPushed();
    }

    public function test_data_prepare_writes_prepared_jpeg(): void
    {
        $bytes = self::jpegBytes();
        $file = $this->makeFile([
            'scraping_status' => ScrapingStatus::PROCESSING,
            'scraping_stage' => ScrapingStage::DATA_PREPARING,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $path = 'files/'.$file->id.'/data.bin';
        $file->path = $path;
        $file->save();

        Storage::put($path, $bytes);

        Queue::fake();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame(ScrapingStage::ENRICHMENT, $file->scraping_stage);
        Storage::assertExists($file->preparedImageStoragePath());
        Queue::assertPushed(ScrapeFileJobDispatcher::class, 1);
    }

    public function test_enrichment_sets_description_from_file_vision(): void
    {
        $bytes = self::jpegBytes();

        $file = $this->makeFile([
            'scraping_status' => ScrapingStatus::PROCESSING,
            'scraping_stage' => ScrapingStage::ENRICHMENT,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $path = 'files/'.$file->id.'/data.bin';
        $file->path = $path;
        $file->save();

        Storage::put($path, $bytes);
        $preparedPath = $file->preparedImageStoragePath();
        Storage::put($preparedPath, $bytes);

        $info = (new FileInformation)
            ->setFilePath($preparedPath)
            ->setExtension('jpg')
            ->setMimeType('image/jpeg')
            ->setConfidence(0.9)
            ->setDescription('A scenic landscape.');

        FileVision::shouldReceive('describe')
            ->once()
            ->with($preparedPath)
            ->andReturn($info);

        Queue::fake();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame('A scenic landscape.', $file->description);
        $this->assertSame(ScrapingStage::FINISHING, $file->scraping_stage);
        Queue::assertPushed(ScrapeFileJobDispatcher::class, 1);
    }

    public function test_finishing_sets_success_and_runs_embedding(): void
    {
        $bytes = self::jpegBytes();
        $fakeVector = new Vector(Embeddings::fakeEmbedding((int) config('vectordb.drivers.pgvector.default_dimension')));

        $file = $this->makeFile([
            'scraping_status' => ScrapingStatus::PROCESSING,
            'scraping_stage' => ScrapingStage::FINISHING,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'description' => 'Ready to embed.',
        ]);
        $path = 'files/'.$file->id.'/data.bin';
        $file->path = $path;
        $file->save();

        Storage::put($path, $bytes);
        Storage::put($file->preparedImageStoragePath(), $bytes);

        TextEmbedding::shouldReceive('embed')
            ->once()
            ->with('Ready to embed.')
            ->andReturn($fakeVector);

        Queue::fake();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame(ScrapingStatus::SUCCESS, $file->scraping_status);
        $this->assertNull($file->scraping_stage);
        $this->assertTrue($file->isEmbedded());
        Queue::assertNothingPushed();
    }

    public function test_full_pipeline_completes_in_one_handle_with_sync_queue(): void
    {
        $bytes = self::jpegBytes();
        $fakeVector = new Vector(Embeddings::fakeEmbedding((int) config('vectordb.drivers.pgvector.default_dimension')));

        Scraper::shouldReceive('fetch')
            ->once()
            ->andReturn(new Response(200, ['Content-Type' => 'image/jpeg'], $bytes));

        $info = (new FileInformation)
            ->setFilePath('x')
            ->setExtension('jpg')
            ->setMimeType('image/jpeg')
            ->setConfidence(0.9)
            ->setDescription('Pipeline description.');

        FileVision::shouldReceive('describe')
            ->once()
            ->with(Mockery::on(fn (string $path): bool => str_ends_with($path, 'prepared-image.jpg')))
            ->andReturn($info);

        TextEmbedding::shouldReceive('embed')
            ->once()
            ->with('Pipeline description.')
            ->andReturn($fakeVector);

        $file = $this->makeFile();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame(ScrapingStatus::SUCCESS, $file->scraping_status);
        $this->assertNull($file->scraping_stage);
        $this->assertSame('Pipeline description.', $file->description);
        $this->assertTrue($file->isEmbedded());
        Storage::assertExists('files/'.$file->id.'/data.bin');
        Storage::assertExists($file->preparedImageStoragePath());
    }

    public function test_max_attempts_marks_failed_without_running_stages(): void
    {
        $file = $this->makeFile([
            'scraping_status' => ScrapingStatus::QUEUED,
            'attempts' => 5,
        ]);

        Scraper::shouldReceive('fetch')->never();

        (new ScrapeFileJob($file))->handle();

        $file->refresh();
        $this->assertSame(ScrapingStatus::FAILED, $file->scraping_status);
        $this->assertNull($file->scraping_stage);
    }
}
