<?php

namespace Tests\Unit\Contracts\Model\Article;

use App\Contracts\Model\Article\StageData\RectificationStageData;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Critic\Rectification;
use Tests\TestCase;

class RectificationStageDataTest extends TestCase
{
    public function test_runs_critics_by_order_not_config_list_order(): void
    {
        $stageData = new RectificationStageData;
        $stageData->ensureCriticsInitialized([
            ['purpose' => 'clarity', 'order' => 2],
            ['purpose' => 'voice', 'order' => 0],
            ['purpose' => 'structure', 'order' => 1],
        ]);

        $this->assertSame('voice', $stageData->getNextPendingCritic()?->getPurpose());

        $stageData->flagCriticAwaitingRectification('voice', [
            (new Criticism)->setPurpose('voice')->setRemarks(['fix voice']),
        ]);

        $this->assertSame('voice', $stageData->getCriticAwaitingRectification()?->getPurpose());
        $this->assertNull($stageData->getNextPendingCritic());

        $stageData->markCriticDone('voice');

        $this->assertSame(1, $stageData->getCriticState('voice')?->getRound());
        $this->assertFalse($stageData->getCriticState('structure')?->isFinished());
        $this->assertSame('structure', $stageData->getNextPendingCritic()?->getPurpose());
    }

    public function test_reinitializes_when_critic_config_changes(): void
    {
        $stageData = RectificationStageData::fromArray([
            'critics' => [
                ['purpose' => 'voice', 'order' => 0, 'finished' => true, 'round' => 1],
            ],
        ]);

        $stageData->ensureCriticsInitialized([
            ['purpose' => 'voice', 'order' => 0],
            ['purpose' => 'clarity', 'order' => 1],
        ]);

        $this->assertNotNull($stageData->getCriticState('clarity'));
        $this->assertFalse($stageData->getCriticState('clarity')?->isFinished());
        $this->assertSame(0, $stageData->getCriticState('clarity')?->getRound());
    }

    public function test_round_trip_serializes_critic_map(): void
    {
        $stageData = new RectificationStageData;
        $stageData->ensureCriticsInitialized([
            ['purpose' => 'voice', 'order' => 0],
        ]);
        $stageData->flagCriticAwaitingRectification('voice', [
            (new Criticism)->setPurpose('voice')->setRemarks(['note']),
        ]);
        $restored = RectificationStageData::fromArray($stageData->toArray());

        $this->assertSame(1, $restored->getCriticState('voice')?->getRound());
        $this->assertNotNull($restored->getCriticAwaitingRectification());
        $this->assertSame('voice', $restored->getCriticAwaitingRectification()?->getPurpose());
        $this->assertSame(['note'], $restored->getCriticState('voice')?->getPendingCriticisms()[0]->getRemarks());
    }

    public function test_no_criticism_in_pass_returns_null_for_awaiting(): void
    {
        $stageData = new RectificationStageData;
        $stageData->ensureCriticsInitialized([
            ['purpose' => 'voice', 'order' => 0],
        ]);

        $this->assertNull($stageData->getCriticAwaitingRectification());
    }

    public function test_all_critics_visited_after_mark_done(): void
    {
        $stageData = new RectificationStageData;
        $stageData->ensureCriticsInitialized([
            ['purpose' => 'voice', 'order' => 0],
        ]);

        $stageData->markCriticDone('voice');

        $this->assertNull($stageData->getNextPendingCritic());
        $this->assertTrue($stageData->getCriticState('voice')?->isFinished());
        $this->assertSame(0, $stageData->getCriticState('voice')?->getRound());
    }

    public function test_same_order_breaks_ties_by_purpose(): void
    {
        $stageData = new RectificationStageData;
        $stageData->ensureCriticsInitialized([
            ['purpose' => 'zebra', 'order' => 0],
            ['purpose' => 'alpha', 'order' => 0],
        ]);

        $this->assertSame('alpha', $stageData->getNextPendingCritic()?->getPurpose());

        $stageData->markCriticDone('alpha');

        $this->assertSame('zebra', $stageData->getNextPendingCritic()?->getPurpose());
    }

    public function test_second_pass_runs_all_critics_again_after_reset(): void
    {
        $stageData = new RectificationStageData;
        $stageData->ensureCriticsInitialized([
            ['purpose' => 'voice', 'order' => 0],
            ['purpose' => 'structure', 'order' => 1],
        ]);

        $stageData->flagCriticAwaitingRectification('voice', [
            (new Criticism)->setPurpose('voice')->setRemarks(['note']),
        ]);
        $stageData->markCriticDone('voice');
        $stageData->markCriticDone('structure');
        $stageData->advancePass();
        $stageData->resetForNextPass();

        $this->assertSame(2, $stageData->getCriticState('voice')?->getRound());
        $this->assertSame(1, $stageData->getCriticState('structure')?->getRound());
        $this->assertFalse($stageData->getCriticState('voice')?->isFinished());
        $this->assertSame('voice', $stageData->getNextPendingCritic()?->getPurpose());
    }

    public function test_rectifications_live_on_critic_state_and_aggregate_in_order(): void
    {
        $stageData = new RectificationStageData;
        $stageData->ensureCriticsInitialized([
            ['purpose' => 'voice', 'order' => 0],
            ['purpose' => 'structure', 'order' => 1],
        ]);

        $stageData->addRectificationsForCritic('voice', [
            (new Rectification)->setReference('intro')->setConfidence(0.9),
        ]);
        $stageData->addRectificationsForCritic('structure', [
            (new Rectification)->setReference('body')->setConfidence(0.8),
        ]);

        $this->assertSame('intro', $stageData->getCriticState('voice')?->getRectifications()[0]->getReference());
        $this->assertCount(2, $stageData->getRectifications());
        $this->assertSame(['intro', 'body'], array_map(
            static fn (Rectification $r): ?string => $r->getReference(),
            $stageData->getRectifications()
        ));

        $restored = RectificationStageData::fromArray($stageData->toArray());

        $this->assertSame('body', $restored->getCriticState('structure')?->getRectifications()[0]->getReference());
        $this->assertCount(2, $restored->getRectifications());
    }

    public function test_has_reached_max_rounds_from_highest_critic_round(): void
    {
        $stageData = new RectificationStageData;
        $stageData->ensureCriticsInitialized([
            ['purpose' => 'voice', 'order' => 0],
            ['purpose' => 'structure', 'order' => 1],
        ]);

        $stageData->getCriticState('voice')?->setRound(2);
        $stageData->getCriticState('structure')?->setRound(1);

        $this->assertTrue($stageData->hasReachedMaxRounds(2));

        $stageData->getCriticState('voice')?->setRound(1);

        $this->assertFalse($stageData->hasReachedMaxRounds(2));
    }

}
