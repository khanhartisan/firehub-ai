<?php

namespace Tests\Unit\ModelListeners\ArticleIntent;

use App\ModelListeners\ArticleIntent\UpdateCounter;
use App\Models\ArticleIntent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class UpdateCounterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_created_increments_article_intents_count_and_intent_articles_count(): void
    {
        $articleRelation = Mockery::mock(BelongsTo::class);
        $articleRelation->shouldReceive('increment')->once()->with('intents_count');

        $intentRelation = Mockery::mock(BelongsTo::class);
        $intentRelation->shouldReceive('increment')->once()->with('articles_count');

        $articleIntent = Mockery::mock(ArticleIntent::class);
        $articleIntent->shouldReceive('article')->once()->andReturn($articleRelation);
        $articleIntent->shouldReceive('intent')->once()->andReturn($intentRelation);

        (new UpdateCounter())->handle($articleIntent, 'created');
    }

    public function test_deleted_decrements_article_intents_count_and_intent_articles_count(): void
    {
        $articleRelation = Mockery::mock(BelongsTo::class);
        $articleRelation->shouldReceive('decrement')->once()->with('intents_count');

        $intentRelation = Mockery::mock(BelongsTo::class);
        $intentRelation->shouldReceive('decrement')->once()->with('articles_count');

        $articleIntent = Mockery::mock(ArticleIntent::class);
        $articleIntent->shouldReceive('article')->once()->andReturn($articleRelation);
        $articleIntent->shouldReceive('intent')->once()->andReturn($intentRelation);

        (new UpdateCounter())->handle($articleIntent, 'deleted');
    }

    public function test_unhandled_event_does_not_call_relations(): void
    {
        $articleIntent = Mockery::mock(ArticleIntent::class);
        $articleIntent->shouldReceive('article')->never();
        $articleIntent->shouldReceive('intent')->never();

        (new UpdateCounter())->handle($articleIntent, 'updated');
    }
}
