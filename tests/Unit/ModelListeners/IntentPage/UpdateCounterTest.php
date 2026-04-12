<?php

namespace Tests\Unit\ModelListeners\IntentPage;

use App\ModelListeners\IntentPage\UpdateCounter;
use App\Models\IntentPage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class UpdateCounterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_created_increments_page_intents_count_and_intent_pages_count(): void
    {
        $pageRelation = Mockery::mock(BelongsTo::class);
        $pageRelation->shouldReceive('increment')->once()->with('intents_count');

        $intentRelation = Mockery::mock(BelongsTo::class);
        $intentRelation->shouldReceive('increment')->once()->with('pages_count');

        $intentPage = Mockery::mock(IntentPage::class);
        $intentPage->shouldReceive('page')->once()->andReturn($pageRelation);
        $intentPage->shouldReceive('intent')->once()->andReturn($intentRelation);

        (new UpdateCounter())->handle($intentPage, 'created');
    }

    public function test_deleted_decrements_page_intents_count_and_intent_pages_count(): void
    {
        $pageRelation = Mockery::mock(BelongsTo::class);
        $pageRelation->shouldReceive('decrement')->once()->with('intents_count');

        $intentRelation = Mockery::mock(BelongsTo::class);
        $intentRelation->shouldReceive('decrement')->once()->with('pages_count');

        $intentPage = Mockery::mock(IntentPage::class);
        $intentPage->shouldReceive('page')->once()->andReturn($pageRelation);
        $intentPage->shouldReceive('intent')->once()->andReturn($intentRelation);

        (new UpdateCounter())->handle($intentPage, 'deleted');
    }

    public function test_unhandled_event_does_not_call_relations(): void
    {
        $intentPage = Mockery::mock(IntentPage::class);
        $intentPage->shouldReceive('page')->never();
        $intentPage->shouldReceive('intent')->never();

        (new UpdateCounter())->handle($intentPage, 'updated');
    }
}
