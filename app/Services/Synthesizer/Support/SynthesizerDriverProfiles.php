<?php

namespace App\Services\Synthesizer\Support;

/**
 * Orchestrator profiles: wire each subservice to a driver name from synthesizer.{subservice}.drivers.
 */
final class SynthesizerDriverProfiles
{
    public static function basic(): array
    {
        return [
            'idea_forge' => [
                'driver' => 'basic',
                'advisors' => [
                    ['driver' => 'basic', 'weight' => 1.0],
                ],
                'auditor' => 'basic',
                'picker' => 'basic',
            ],
            'researcher' => 'basic',
            'brief_builder' => 'basic',
            'outline_builder' => 'basic',
            'editor' => 'basic',
            'critics' => [
                ['driver' => 'basic', 'purpose' => 'voice', 'order' => 0],
                ['driver' => 'basic', 'purpose' => 'structure', 'order' => 1],
                ['driver' => 'basic', 'purpose' => 'clarity', 'order' => 2, 'max_rectification_rounds' => 1],
                ['driver' => 'basic', 'purpose' => 'concision', 'order' => 3],
                ['driver' => 'basic', 'purpose' => 'fingerprint', 'order' => 4],
                ['driver' => 'basic', 'purpose' => 'evidence', 'order' => 5],
                ['driver' => 'basic', 'purpose' => 'general', 'order' => 6],
            ],
            'writer' => 'basic',
            'illustration' => [
                'director' => 'basic',
                'illustrators' => ['basic'],
            ],
        ];
    }

    public static function openai(): array
    {
        return [
            'idea_forge' => [
                'driver' => 'basic',
                'advisors' => [
                    ['driver' => 'openai', 'weight' => 1.0],
                    ['driver' => 'openai_expansion', 'weight' => 1.0],
                ],
                'auditor' => 'openai',
                'picker' => 'openai',
            ],
            'researcher' => 'openai',
            'brief_builder' => 'openai',
            'outline_builder' => 'openai',
            'editor' => 'openai',
            'critics' => [
                ['driver' => 'openai', 'purpose' => 'voice', 'order' => 0],
                ['driver' => 'openai', 'purpose' => 'structure', 'order' => 1],
                ['driver' => 'openai', 'purpose' => 'clarity', 'order' => 2, 'max_rectification_rounds' => 2],
                ['driver' => 'openai', 'purpose' => 'concision', 'order' => 3],
                ['driver' => 'openai', 'purpose' => 'evidence', 'order' => 4],
                ['driver' => 'openai', 'purpose' => 'fingerprint', 'order' => 5],
                ['driver' => 'openai', 'purpose' => 'general', 'order' => 6],
            ],
            'writer' => 'openai',
            'illustration' => [
                'director' => 'openai',
                'illustrators' => [
                    env('SYNTHESIZER_OPENAI_ILLUSTRATOR_DRIVER', 'openai'),
                ],
            ],
        ];
    }

    /**
     * Full pipeline using OpenAI-compatible API drivers for every AI subservice.
     */
    public static function openaiCompatible(): array
    {
        return [
            'idea_forge' => [
                'driver' => 'basic',
                'advisors' => [
                    ['driver' => 'openai_compatible', 'weight' => 1.0],
                    ['driver' => 'openai_compatible_expansion', 'weight' => 1.0],
                ],
                'auditor' => 'openai_compatible',
                'picker' => 'openai_compatible',
            ],
            'researcher' => 'openai_compatible',
            'brief_builder' => 'openai_compatible',
            'outline_builder' => 'openai_compatible',
            'editor' => 'openai_compatible',
            'critics' => [
                ['driver' => 'openai_compatible', 'purpose' => 'voice', 'order' => 0],
                ['driver' => 'openai_compatible', 'purpose' => 'structure', 'order' => 1],
                ['driver' => 'openai_compatible', 'purpose' => 'clarity', 'order' => 2],
                ['driver' => 'openai_compatible', 'purpose' => 'concision', 'order' => 3],
                ['driver' => 'openai_compatible', 'purpose' => 'fingerprint', 'order' => 4],
                ['driver' => 'openai_compatible', 'purpose' => 'evidence', 'order' => 5],
                ['driver' => 'openai_compatible', 'purpose' => 'general', 'order' => 6],
            ],
            'writer' => 'openai_compatible',
            'illustration' => [
                'director' => 'openai_compatible',
                'illustrators' => [
                    env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_DRIVER', 'openai_compatible'),
                ],
            ],
        ];
    }
}
