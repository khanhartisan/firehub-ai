<?php

namespace Tests\Unit\Models\Concerns;

use App\Enums\PlatformType;
use App\Models\Meta;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_morph_many_relation(): void
    {
        $platform = $this->createPlatform();

        $relation = $platform->meta();

        $this->assertInstanceOf(MorphMany::class, $relation);
        $this->assertInstanceOf(Meta::class, $relation->getRelated());
    }

    public function test_put_meta_creates_record_with_morph_attributes(): void
    {
        $platform = $this->createPlatform();

        $this->assertTrue($platform->putMeta('api_version', '2'));

        $this->assertDatabaseHas('meta', [
            'metable_type' => $platform->getMorphClass(),
            'metable_id' => $platform->id,
            'key' => 'api_version',
            'value' => '2',
        ]);
    }

    public function test_put_meta_updates_existing_key(): void
    {
        $platform = $this->createPlatform();
        $platform->putMeta('api_version', '1');

        $this->assertTrue($platform->putMeta('api_version', '2'));

        $this->assertDatabaseCount('meta', 1);
        $this->assertSame('2', $platform->fresh()->getMetaValue('api_version'));
    }

    public function test_get_meta_returns_meta_model_for_key(): void
    {
        $platform = $this->createPlatform();
        $platform->putMeta('region', 'us-east');

        $meta = $platform->getMeta('region');

        $this->assertInstanceOf(Meta::class, $meta);
        $this->assertSame('region', $meta->key);
        $this->assertSame('us-east', $meta->value);
        $this->assertTrue($platform->is($meta->metable));
    }

    public function test_get_meta_returns_null_for_missing_key(): void
    {
        $platform = $this->createPlatform();

        $this->assertNull($platform->getMeta('missing'));
    }

    public function test_get_meta_value_returns_stored_value(): void
    {
        $platform = $this->createPlatform();
        $platform->putMeta('note', 'hello');

        $this->assertSame('hello', $platform->getMetaValue('note'));
    }

    public function test_get_meta_value_returns_null_for_missing_key(): void
    {
        $platform = $this->createPlatform();

        $this->assertNull($platform->getMetaValue('missing'));
    }

    public function test_put_meta_stores_null_value(): void
    {
        $platform = $this->createPlatform();

        $platform->putMeta('cleared_at', null);

        $this->assertDatabaseHas('meta', [
            'metable_id' => $platform->id,
            'key' => 'cleared_at',
            'value' => null,
        ]);
        $this->assertNull($platform->getMetaValue('cleared_at'));
    }

    public function test_meta_is_scoped_per_model_instance(): void
    {
        $first = $this->createPlatform('First');
        $second = $this->createPlatform('Second');

        $first->putMeta('label', 'alpha');
        $second->putMeta('label', 'beta');

        $this->assertSame('alpha', $first->fresh()->getMetaValue('label'));
        $this->assertSame('beta', $second->fresh()->getMetaValue('label'));
        $this->assertCount(1, $first->fresh()->meta);
        $this->assertCount(1, $second->fresh()->meta);
    }

    private function createPlatform(string $name = 'Test Platform'): Platform
    {
        $platform = new Platform;
        $platform->name = $name;
        $platform->type = PlatformType::FLYCMS;
        $platform->save();

        return $platform;
    }
}
