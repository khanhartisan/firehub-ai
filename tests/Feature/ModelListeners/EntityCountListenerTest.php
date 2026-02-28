<?php

namespace Tests\Feature\ModelListeners;

use App\Enums\EntityType;
use App\Enums\ScrapingStatus;
use App\Models\Entity;
use App\Models\EntityCount;
use App\Models\Source;
use App\Models\Tag;
use App\Models\Vertical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityCountListenerTest extends TestCase
{
    use RefreshDatabase;

    private function entityCountFor(Source|Vertical|Tag $countable, EntityType $entityType, ScrapingStatus $scrapingStatus): int
    {
        $record = EntityCount::query()
            ->where('countable_type', $countable->getMorphClass())
            ->where('countable_id', $countable->id)
            ->where('entity_type', $entityType)
            ->where('scraping_status', $scrapingStatus)
            ->first();

        return $record?->count ?? 0;
    }

    private function createSource(?string $baseUrl = null): Source
    {
        return Source::create(['base_url' => $baseUrl ?? 'https://example.com/' . uniqid()]);
    }

    private function createVertical(?string $name = null): Vertical
    {
        return Vertical::create(['name' => $name ?? 'vertical-' . uniqid()]);
    }

    private function createTag(?string $name = null): Tag
    {
        return Tag::create(['name' => $name ?? 'tag-' . uniqid()]);
    }

    private function createEntity(Source $source, array $overrides = []): Entity
    {
        $url = $overrides['url'] ?? 'https://example.com/page-' . uniqid();
        return Entity::create(array_merge([
            'source_id' => $source->id,
            'url' => $url,
            'url_hash' => sha1($url),
        ], $overrides));
    }

    public function test_entity_created_increments_source_count_for_entity_type_and_scraping_status(): void
    {
        $source = $this->createSource();
        $this->assertSame(0, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::PENDING));

        $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);

        $source->refresh();
        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::PENDING));
    }

    public function test_entity_created_then_verticals_attached_only_source_incremented(): void
    {
        $source = $this->createSource();
        $vertical1 = $this->createVertical();
        $vertical2 = $this->createVertical();

        $entity = $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::QUEUED,
        ]);
        $entity->verticals()->attach([$vertical1->id, $vertical2->id]);

        $source->refresh();
        $vertical1->refresh();
        $vertical2->refresh();

        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::QUEUED));
        $this->assertSame(1, $this->entityCountFor($vertical1, EntityType::PAGE, ScrapingStatus::QUEUED));
        $this->assertSame(1, $this->entityCountFor($vertical2, EntityType::PAGE, ScrapingStatus::QUEUED));
    }

    public function test_entity_tags_synced_increments_tag_counts(): void
    {
        $source = $this->createSource();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        $entity = $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);
        $entity->tags()->sync([$tag1->id, $tag2->id]);

        $tag1->refresh();
        $tag2->refresh();

        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($tag1, EntityType::PAGE, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($tag2, EntityType::PAGE, ScrapingStatus::PENDING));
    }

    public function test_entity_tags_synced_then_synced_again_updates_counts(): void
    {
        $source = $this->createSource();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();
        $tag3 = $this->createTag();

        $entity = $this->createEntity($source, [
            'type' => EntityType::IMAGE,
            'scraping_status' => ScrapingStatus::SUCCESS,
        ]);
        $entity->tags()->sync([$tag1->id, $tag2->id]);

        $this->assertSame(1, $this->entityCountFor($tag1, EntityType::IMAGE, ScrapingStatus::SUCCESS));
        $this->assertSame(1, $this->entityCountFor($tag2, EntityType::IMAGE, ScrapingStatus::SUCCESS));
        $this->assertSame(0, $this->entityCountFor($tag3, EntityType::IMAGE, ScrapingStatus::SUCCESS));

        $entity->tags()->sync([$tag2->id, $tag3->id]);

        $tag1->refresh();
        $tag2->refresh();
        $tag3->refresh();
        $this->assertSame(0, $this->entityCountFor($tag1, EntityType::IMAGE, ScrapingStatus::SUCCESS));
        $this->assertSame(1, $this->entityCountFor($tag2, EntityType::IMAGE, ScrapingStatus::SUCCESS));
        $this->assertSame(1, $this->entityCountFor($tag3, EntityType::IMAGE, ScrapingStatus::SUCCESS));
    }

    public function test_entity_tags_synced_empty_detaches_all(): void
    {
        $source = $this->createSource();
        $tag = $this->createTag();

        $entity = $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::QUEUED,
        ]);
        $entity->tags()->sync([$tag->id]);

        $tag->refresh();
        $this->assertSame(1, $this->entityCountFor($tag, EntityType::PAGE, ScrapingStatus::QUEUED));

        $entity->tags()->sync([]);

        $tag->refresh();
        $this->assertSame(0, $this->entityCountFor($tag, EntityType::PAGE, ScrapingStatus::QUEUED));
    }

    public function test_entity_verticals_synced_increments_vertical_counts(): void
    {
        $source = $this->createSource();
        $vertical1 = $this->createVertical();
        $vertical2 = $this->createVertical();

        $entity = $this->createEntity($source, [
            'type' => EntityType::DOCUMENT,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);
        $entity->verticals()->sync([$vertical1->id, $vertical2->id]);

        $vertical1->refresh();
        $vertical2->refresh();

        $this->assertSame(1, $this->entityCountFor($source, EntityType::DOCUMENT, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($vertical1, EntityType::DOCUMENT, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($vertical2, EntityType::DOCUMENT, ScrapingStatus::PENDING));
    }

    public function test_entity_created_without_verticals_only_updates_source_count(): void
    {
        $source = $this->createSource();
        $vertical = $this->createVertical();

        $this->createEntity($source, [
            'type' => EntityType::IMAGE,
            'scraping_status' => ScrapingStatus::SUCCESS,
        ]);

        $this->assertSame(1, $this->entityCountFor($source, EntityType::IMAGE, ScrapingStatus::SUCCESS));
        $this->assertSame(0, $this->entityCountFor($vertical, EntityType::IMAGE, ScrapingStatus::SUCCESS));
    }

    public function test_entity_deleted_decrements_source_and_vertical_counts(): void
    {
        $source = $this->createSource();
        $vertical = $this->createVertical();
        $entity = $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);
        $entity->verticals()->attach($vertical->id);

        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($vertical, EntityType::PAGE, ScrapingStatus::PENDING));

        $entity->load('verticals');
        $entity->delete();

        $source->refresh();
        $vertical->refresh();
        $this->assertSame(0, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::PENDING));
        $this->assertSame(0, $this->entityCountFor($vertical, EntityType::PAGE, ScrapingStatus::PENDING));
    }

    public function test_entity_type_updated_decrements_old_and_increments_new_on_source_and_verticals(): void
    {
        $source = $this->createSource();
        $vertical = $this->createVertical();
        $entity = $this->createEntity($source, [
            'type' => EntityType::UNCLASSIFIED,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);
        $entity->verticals()->attach($vertical->id);

        $this->assertSame(1, $this->entityCountFor($source, EntityType::UNCLASSIFIED, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($vertical, EntityType::UNCLASSIFIED, ScrapingStatus::PENDING));

        $entity->load('verticals');
        $entity->update(['type' => EntityType::PAGE]);

        $source->refresh();
        $vertical->refresh();
        $this->assertSame(0, $this->entityCountFor($source, EntityType::UNCLASSIFIED, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::PENDING));
        $this->assertSame(0, $this->entityCountFor($vertical, EntityType::UNCLASSIFIED, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($vertical, EntityType::PAGE, ScrapingStatus::PENDING));
    }

    public function test_entity_scraping_status_updated_decrements_old_and_increments_new(): void
    {
        $source = $this->createSource();
        $vertical = $this->createVertical();
        $entity = $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::QUEUED,
        ]);
        $entity->verticals()->attach($vertical->id);

        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::QUEUED));
        $this->assertSame(1, $this->entityCountFor($vertical, EntityType::PAGE, ScrapingStatus::QUEUED));

        $entity->load('verticals');
        $entity->update(['scraping_status' => ScrapingStatus::SUCCESS]);

        $source->refresh();
        $vertical->refresh();
        $this->assertSame(0, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::QUEUED));
        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::SUCCESS));
        $this->assertSame(0, $this->entityCountFor($vertical, EntityType::PAGE, ScrapingStatus::QUEUED));
        $this->assertSame(1, $this->entityCountFor($vertical, EntityType::PAGE, ScrapingStatus::SUCCESS));
    }

    public function test_entity_type_and_scraping_status_both_updated_decrements_old_and_increments_new(): void
    {
        $source = $this->createSource();
        $entity = $this->createEntity($source, [
            'type' => EntityType::UNCLASSIFIED,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);

        $this->assertSame(1, $this->entityCountFor($source, EntityType::UNCLASSIFIED, ScrapingStatus::PENDING));

        $entity->update([
            'type' => EntityType::DOCUMENT,
            'scraping_status' => ScrapingStatus::FAILED,
        ]);

        $source->refresh();
        $this->assertSame(0, $this->entityCountFor($source, EntityType::UNCLASSIFIED, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($source, EntityType::DOCUMENT, ScrapingStatus::FAILED));
    }

    public function test_entity_updated_without_type_or_scraping_status_change_does_not_change_counts(): void
    {
        $source = $this->createSource();
        $entity = $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::SUCCESS,
            'url' => 'https://example.com/page',
            'url_hash' => sha1('https://example.com/page'),
        ]);

        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::SUCCESS));

        $entity->update([
            'url' => 'https://example.com/other',
            'url_hash' => sha1('https://example.com/other'),
        ]);

        $source->refresh();
        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::SUCCESS));
    }

    public function test_multiple_entities_same_type_and_status_accumulate_source_count(): void
    {
        $source = $this->createSource();

        $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);
        $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);
        $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);

        $source->refresh();
        $this->assertSame(3, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::PENDING));
    }

    public function test_different_entity_types_and_statuses_are_counted_separately(): void
    {
        $source = $this->createSource();

        $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);
        $this->createEntity($source, [
            'type' => EntityType::PAGE,
            'scraping_status' => ScrapingStatus::QUEUED,
        ]);
        $this->createEntity($source, [
            'type' => EntityType::IMAGE,
            'scraping_status' => ScrapingStatus::PENDING,
        ]);

        $source->refresh();
        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::PENDING));
        $this->assertSame(1, $this->entityCountFor($source, EntityType::PAGE, ScrapingStatus::QUEUED));
        $this->assertSame(1, $this->entityCountFor($source, EntityType::IMAGE, ScrapingStatus::PENDING));
    }
}
