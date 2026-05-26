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
                ['driver' => 'basic', 'purpose' => 'voice'],
                ['driver' => 'basic', 'purpose' => 'structure'],
                ['driver' => 'basic', 'purpose' => 'clarity'],
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
                ['driver' => 'openai', 'purpose' => 'voice'],
                ['driver' => 'openai', 'purpose' => 'structure'],
                ['driver' => 'openai', 'purpose' => 'clarity'],
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
                ['driver' => 'openai_compatible', 'purpose' => 'voice'],
                ['driver' => 'openai_compatible', 'purpose' => 'structure'],
                ['driver' => 'openai_compatible', 'purpose' => 'clarity'],
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
