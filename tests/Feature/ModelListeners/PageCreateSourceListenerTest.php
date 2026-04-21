<?php

namespace Tests\Feature\ModelListeners;

use App\Models\Page;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageCreateSourceListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_source_and_sets_source_id_when_missing(): void
    {
        $page = Page::query()->create([
            'url' => 'https://Example.com/articles/hello-world?ref=test',
        ]);

        $page->refresh();

        $this->assertNotNull($page->source_id);

        $source = Source::query()->find($page->source_id);
        $this->assertNotNull($source);
        $this->assertSame('https://example.com/', $source->base_url);

        $this->assertSame(1, Source::query()->where('base_url', 'https://example.com/')->count());
    }

    public function test_it_does_not_override_source_id_when_already_set(): void
    {
        $existingSource = Source::query()->create([
            'base_url' => 'https://kept-source.com/',
        ]);

        $page = Page::query()->create([
            'source_id' => $existingSource->id,
            'url' => 'https://another-host.com/path',
        ]);

        $page->refresh();

        $this->assertSame($existingSource->id, $page->source_id);
        $this->assertSame(0, Source::query()->where('base_url', 'https://another-host.com/')->count());
    }
}
