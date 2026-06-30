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
                CriticProfileEntry::entry('basic', 'voice', 0),
                CriticProfileEntry::entry('basic', 'structure', 1),
                CriticProfileEntry::entry('basic', 'clarity', 2, ['max_rectification_rounds' => 1]),
                CriticProfileEntry::entry('basic', 'concision', 3),
                CriticProfileEntry::entry('basic', 'fingerprint', 4),
                CriticProfileEntry::entry('basic', 'evidence', 5),
                CriticProfileEntry::entry('basic', 'general', 6),
            ],
            'writer' => 'basic',
            'tagger' => 'basic',
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
                CriticProfileEntry::entry('openai', 'general', 0),
                CriticProfileEntry::entry('openai', 'voice', 1),
                CriticProfileEntry::entry('openai', 'structure', 2),
                CriticProfileEntry::entry('openai', 'clarity', 3),
                CriticProfileEntry::entry('openai', 'evidence', 4),
                CriticProfileEntry::entry('openai', 'concision', 5),
                CriticProfileEntry::entry('openai', 'fingerprint', 6),
            ],
            'writer' => 'openai',
            'tagger' => 'openai',
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
                CriticProfileEntry::entry('openai_compatible', 'general', 0),
                CriticProfileEntry::entry('openai_compatible', 'voice', 1),
                CriticProfileEntry::entry('openai_compatible', 'structure', 2),
                CriticProfileEntry::entry('openai_compatible', 'clarity', 3),
                CriticProfileEntry::entry('openai_compatible', 'evidence', 4),
                CriticProfileEntry::entry('openai_compatible', 'concision', 5),
                CriticProfileEntry::entry('openai_compatible', 'fingerprint', 6),
            ],
            'writer' => 'openai_compatible',
            'tagger' => 'openai_compatible',
            'illustration' => [
                'director' => 'openai_compatible',
                'illustrators' => [
                    env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_DRIVER', 'openai_compatible'),
                ],
            ],
        ];
    }
}
