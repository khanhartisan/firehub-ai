<?php

namespace Tests\Unit\Services\Synthesizer\Support;

use App\Services\Synthesizer\Support\CriticProfileEntry;
use Tests\TestCase;

class CriticProfileEntryTest extends TestCase
{
    public function test_entry_includes_default_score_thresholds(): void
    {
        $entry = CriticProfileEntry::entry('openai', 'clarity', 2);

        $this->assertSame(0.5, $entry['min_confidence']);
        $this->assertSame(0.5, $entry['min_importance']);
    }

    public function test_entry_allows_per_critic_threshold_overrides(): void
    {
        $entry = CriticProfileEntry::entry('openai', 'evidence', 4, [
            'min_confidence' => 0.9,
            'min_importance' => 0.75,
        ]);

        $this->assertSame(0.9, $entry['min_confidence']);
        $this->assertSame(0.75, $entry['min_importance']);
    }

    public function test_driver_config_falls_back_when_thresholds_omitted_from_entry(): void
    {
        $config = CriticProfileEntry::driverConfig([
            'driver' => 'openai',
            'purpose' => 'voice',
            'order' => 0,
        ]);

        $this->assertSame([
            'min_confidence' => 0.5,
            'min_importance' => 0.5,
        ], $config);
    }
}
