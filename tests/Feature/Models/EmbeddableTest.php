<?php

namespace Tests\Feature\Models;

use App\Contracts\VectorDB\Vector;
use App\Enums\ScrapableType;
use App\Enums\PageType;
use App\Enums\ScrapingStatus;
use App\Models\Page;
use App\Models\Source;
use App\Models\Tag;
use App\Models\Vertical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class EmbeddableTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_unembedded_returns_only_sources_where_is_embedded_false(): void
    {
        Source::factory()->create(['base_url' => 'https://one.com']);
        Source::factory()->create(['base_url' => 'https://two.com']);
        $embedded = Source::factory()->create(['base_url' => 'https://three.com']);
        $embedded->update(['is_embedded' => true]);

        $unembedded = Source::getUnembedded(10);

        $this->assertCount(2, $unembedded);
        $this->assertTrue($unembedded->pluck('id')->contains(Source::where('base_url', 'https://one.com')->value('id')));
        $this->assertTrue($unembedded->pluck('id')->contains(Source::where('base_url', 'https://two.com')->value('id')));
        $this->assertFalse($unembedded->pluck('id')->contains($embedded->id));
    }

    public function test_get_unembedded_respects_limit(): void
    {
        Source::factory()->create(['base_url' => 'https://a.com']);
        Source::factory()->create(['base_url' => 'https://b.com']);
        Source::factory()->create(['base_url' => 'https://c.com']);

        $result = Source::getUnembedded(2);

        $this->assertCount(2, $result);
    }

    public function test_get_unembedded_returns_empty_when_all_embedded(): void
    {
        $s1 = Source::factory()->create(['base_url' => 'https://x.com']);
        $s2 = Source::factory()->create(['base_url' => 'https://y.com']);
        $s1->update(['is_embedded' => true]);
        $s2->update(['is_embedded' => true]);

        $this->assertCount(0, Source::getUnembedded(10));
    }

    public function test_get_unembedded_works_on_entity_vertical_tag(): void
    {
        $source = Source::factory()->create(['base_url' => 'https://example.com']);
        $entity = Page::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
            'type' => ScrapableType::PAGE,
            'page_type' => PageType::DETAIL,
            'scraping_status' => ScrapingStatus::SUCCESS,
        ]);
        $vertical = Vertical::create(['name' => 'v-' . uniqid()]);

        $this->assertCount(1, Page::getUnembedded(10));
        $this->assertCount(1, Vertical::getUnembedded(10));

        $entity->update(['is_embedded' => true]);
        $vertical->update(['is_embedded' => true]);

        $this->assertCount(0, Page::getUnembedded(10));
        $this->assertCount(0, Vertical::getUnembedded(10));
    }

    public function test_get_unembedded_orders_by_id(): void
    {
        $s1 = Source::factory()->create(['base_url' => 'https://first.com']);
        $s2 = Source::factory()->create(['base_url' => 'https://second.com']);
        $s3 = Source::factory()->create(['base_url' => 'https://third.com']);

        $result = Source::getUnembedded(10);

        $ids = $result->pluck('id')->values()->all();
        $this->assertSame($s1->id, $ids[0]);
        $this->assertSame($s2->id, $ids[1]);
        $this->assertSame($s3->id, $ids[2]);
    }

    public function test_set_embedding_persists_vector_and_is_embedded(): void
    {
        /** @var Source $source */
        $source = Source::factory()->create(['base_url' => 'https://example.com']);
        $vector = new Vector(Embeddings::fakeEmbedding(1536));

        $result = $source->setEmbedding($vector);

        $this->assertTrue($result);
        $source->refresh();
        $this->assertTrue($source->isEmbedded());
        $this->assertInstanceOf(Vector::class, $source->getVector());
        $this->assertEquals($vector->toArray(), $source->getVector()->toArray());
    }

    public function test_set_embedding_removes_model_from_get_unembedded(): void
    {
        /** @var Source $source */
        $source = Source::factory()->create(['base_url' => 'https://example.com']);
        $this->assertCount(1, Source::getUnembedded(10));

        $source->setEmbedding(new Vector(Embeddings::fakeEmbedding(1536)));

        $this->assertCount(0, Source::getUnembedded(10));
    }
}
