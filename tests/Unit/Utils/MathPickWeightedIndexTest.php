<?php

namespace Tests\Unit\Utils;

use App\Utils\Math;
use Tests\TestCase;

class MathPickWeightedIndexTest extends TestCase
{
    public function test_it_returns_null_for_empty_weights(): void
    {
        $this->assertNull(Math::pickWeightedIndex([]));
    }

    public function test_it_picks_index_proportional_to_weight(): void
    {
        mt_srand(1);

        $counts = [0, 0];
        for ($i = 0; $i < 1000; $i++) {
            $index = Math::pickWeightedIndex([0.9, 0.1]);
            $this->assertContains($index, [0, 1]);
            $counts[$index]++;
        }

        $this->assertGreaterThan(800, $counts[0]);
        $this->assertLessThan(200, $counts[1]);
    }

    public function test_it_falls_back_to_uniform_when_all_weights_are_zero(): void
    {
        $indexes = [];
        for ($i = 0; $i < 20; $i++) {
            $indexes[] = Math::pickWeightedIndex([0.0, 0.0, 0.0]);
        }

        $this->assertContains(0, $indexes);
        $this->assertContains(1, $indexes);
        $this->assertContains(2, $indexes);
    }
}
