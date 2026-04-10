<?php

namespace Tests\Unit\ModelListeners\IntentKeyword;

use App\ModelListeners\IntentKeyword\UpdateCounter;
use App\Models\IntentKeyword;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class UpdateCounterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_created_increments_intent_keywords_count_and_keyword_intents_count(): void
    {
        $intentRelation = Mockery::mock(BelongsTo::class);
        $intentRelation->shouldReceive('increment')->once()->with('keywords_count');

        $keywordRelation = Mockery::mock(BelongsTo::class);
        $keywordRelation->shouldReceive('increment')->once()->with('intents_count');

        $intentKeyword = Mockery::mock(IntentKeyword::class);
        $intentKeyword->shouldReceive('intent')->once()->andReturn($intentRelation);
        $intentKeyword->shouldReceive('keyword')->once()->andReturn($keywordRelation);

        (new UpdateCounter())->handle($intentKeyword, 'created');
    }

    public function test_deleted_decrements_intent_keywords_count_and_keyword_intents_count(): void
    {
        $intentRelation = Mockery::mock(BelongsTo::class);
        $intentRelation->shouldReceive('decrement')->once()->with('keywords_count');

        $keywordRelation = Mockery::mock(BelongsTo::class);
        $keywordRelation->shouldReceive('decrement')->once()->with('intents_count');

        $intentKeyword = Mockery::mock(IntentKeyword::class);
        $intentKeyword->shouldReceive('intent')->once()->andReturn($intentRelation);
        $intentKeyword->shouldReceive('keyword')->once()->andReturn($keywordRelation);

        (new UpdateCounter())->handle($intentKeyword, 'deleted');
    }

    public function test_unhandled_event_does_not_call_relations(): void
    {
        $intentKeyword = Mockery::mock(IntentKeyword::class);
        $intentKeyword->shouldReceive('intent')->never();
        $intentKeyword->shouldReceive('keyword')->never();

        (new UpdateCounter())->handle($intentKeyword, 'updated');
    }
}
