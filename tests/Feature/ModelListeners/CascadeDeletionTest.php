<?php

namespace Tests\Feature\ModelListeners;

use App\Jobs\ForceDeleteSnapshots;
use App\Models\Page;
use App\Models\PageTag;
use App\Models\PageVertical;
use App\Models\Snapshot;
use App\Models\Source;
use App\Models\SourceVertical;
use App\Models\Tag;
use App\Models\Vertical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use KhanhArtisan\LaravelBackbone\RelationCascade\Jobs\CascadeDelete;
use Tests\TestCase;

class CascadeDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function runCascadeUntilComplete(int $maxIterations = 5): void
    {
        for ($i = 0; $i < $maxIterations; $i++) {
            CascadeDelete::dispatch();
            ForceDeleteSnapshots::dispatch();
        }
    }

    private function createPage(Source $source, array $overrides = []): Page
    {
        $url = $overrides['url'] ?? 'https://example.com/page-' . uniqid();
        return Page::create(array_merge([
            'source_id' => $source->id,
            'url' => $url,
            'url_hash' => sha1($url),
        ], $overrides));
    }

    public function test_deleting_source_cascades_to_snapshots_and_deletes_snapshot_files(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $entity = $this->createPage($source);

        $filePath = 'snapshots/' . $entity->id . '/' . Str::ulid() . '.html';
        Storage::put($filePath, '<html>Snapshot content</html>');

        $snapshot = Snapshot::create([
            'page_id' => $entity->id,
            'file_path' => $filePath,
            'version' => 1,
        ]);

        $this->assertDatabaseHas('snapshots', ['id' => $snapshot->id]);
        $this->assertTrue(Storage::exists($filePath));

        $source->delete();
        $this->runCascadeUntilComplete();

        $this->assertDatabaseMissing('snapshots', ['id' => $snapshot->id]);
        $this->assertFalse(Storage::exists($filePath));
    }

    public function test_deleting_source_cascades_to_source_vertical_pivot(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $vertical = Vertical::create(['name' => 'vertical-' . uniqid()]);
        $source->verticals()->attach($vertical->id);

        $pivot = SourceVertical::where('source_id', $source->id)->where('vertical_id', $vertical->id)->first();
        $this->assertNotNull($pivot);

        $source->delete();
        $this->runCascadeUntilComplete();

        $this->assertDatabaseMissing('source_vertical', ['id' => $pivot->id]);
    }

    public function test_deleting_entity_cascades_to_snapshots_and_deletes_snapshot_files(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $entity = $this->createPage($source);

        $filePath = 'snapshots/' . $entity->id . '/' . Str::ulid() . '.html';
        Storage::put($filePath, '<html>Snapshot content</html>');

        $snapshot = Snapshot::create([
            'page_id' => $entity->id,
            'file_path' => $filePath,
            'version' => 1,
        ]);

        $this->assertDatabaseHas('snapshots', ['id' => $snapshot->id]);
        $this->assertTrue(Storage::exists($filePath));

        $entity->delete();
        $this->runCascadeUntilComplete();

        $this->assertDatabaseMissing('snapshots', ['id' => $snapshot->id]);
        $this->assertFalse(Storage::exists($filePath));
    }

    public function test_deleting_entity_cascades_to_page_vertical_pivot(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $vertical = Vertical::create(['name' => 'vertical-' . uniqid()]);
        $entity = $this->createPage($source);
        $entity->verticals()->attach($vertical->id);

        $pivot = PageVertical::where('page_id', $entity->id)->where('vertical_id', $vertical->id)->first();
        $this->assertNotNull($pivot);

        $entity->delete();
        $this->runCascadeUntilComplete();

        $this->assertDatabaseMissing('page_vertical', ['id' => $pivot->id]);
    }

    public function test_deleting_entity_cascades_to_page_tag_pivot(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $tag = Tag::create(['name' => 'tag-' . uniqid()]);
        $entity = $this->createPage($source);
        $entity->tags()->attach($tag->id);

        $pivot = PageTag::where('page_id', $entity->id)->where('tag_id', $tag->id)->first();
        $this->assertNotNull($pivot);

        $entity->delete();
        $this->runCascadeUntilComplete();

        $this->assertDatabaseMissing('page_tag', ['id' => $pivot->id]);
    }

    public function test_deleting_vertical_cascades_to_page_vertical_and_source_vertical_pivots(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $vertical = Vertical::create(['name' => 'vertical-' . uniqid()]);
        $entity = $this->createPage($source);

        $source->verticals()->attach($vertical->id);
        $entity->verticals()->attach($vertical->id);

        $entityVerticalPivot = PageVertical::where('vertical_id', $vertical->id)->first();
        $sourceVerticalPivot = SourceVertical::where('vertical_id', $vertical->id)->first();
        $this->assertNotNull($entityVerticalPivot);
        $this->assertNotNull($sourceVerticalPivot);

        $vertical->delete();
        $this->runCascadeUntilComplete();

        $this->assertDatabaseMissing('page_vertical', ['id' => $entityVerticalPivot->id]);
        $this->assertDatabaseMissing('source_vertical', ['id' => $sourceVerticalPivot->id]);
    }

    public function test_deleting_vertical_cascades_to_child_verticals(): void
    {
        $parentVertical = Vertical::create(['name' => 'parent-' . uniqid()]);
        $childVertical = Vertical::create(['name' => 'child-' . uniqid(), 'parent_id' => $parentVertical->id]);

        $this->assertDatabaseHas('verticals', ['id' => $childVertical->id]);

        $parentVertical->delete();
        $this->runCascadeUntilComplete();

        $this->assertDatabaseMissing('verticals', ['id' => $childVertical->id]);
    }

    public function test_deleting_tag_cascades_to_page_tag_pivot(): void
    {
        $source = Source::create(['base_url' => 'https://example.com/' . uniqid()]);
        $tag = Tag::create(['name' => 'tag-' . uniqid()]);
        $entity = $this->createPage($source);
        $entity->tags()->attach($tag->id);

        $pivot = PageTag::where('tag_id', $tag->id)->first();
        $this->assertNotNull($pivot);

        $tag->delete();
        $this->runCascadeUntilComplete();

        $this->assertDatabaseMissing('page_tag', ['id' => $pivot->id]);
    }
}
