<?php

use App\Services\Synthesizer\Author\Drivers\BasicAuthorDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\SynthesizerService;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Synthesizer Driver
    |--------------------------------------------------------------------------
    |
    | Select the default orchestrator driver.
    |
    */

    'default' => env('SYNTHESIZER_DRIVER', 'basic'),

    /*
    |--------------------------------------------------------------------------
    | Synthesizer Drivers
    |--------------------------------------------------------------------------
    |
    | Configure available synthesizer drivers. The "basic" driver is a local,
    | deterministic implementation suitable for development and tests.
    |
    */

    'drivers' => [
        'basic' => [
            'service' => SynthesizerService::class,
            'idea_forge' => [
                'driver' => BasicIdeaForgeDriver::class,
                /*
                | Idea advisors: each entry is either a class-string, or:
                |   ['class' => SomeIdeaAdvisor::class, 'weight' => 2.0]
                | Weight is optional (defaults to 1 on the advisor). Relative weights affect
                | top suggestion selection in the article idea stage.
                */
                'advisors' => [
                    [
                        'class' => BasicIdeaAdvisorDriver::class,
                        'weight' => 1.0,
                    ],
                ],
                'auditor' => BasicIdeaAuditorDriver::class,
                'picker' => BasicIdeaPickerDriver::class,
            ],
            'brief_builder' => [
                'driver' => BasicBriefBuilderDriver::class,
            ],
            'outline_builder' => [
                'driver' => BasicOutlineBuilderDriver::class,
            ],
            'author' => [
                'driver' => BasicAuthorDriver::class,
            ],
        ],

        'openai' => [
            'service' => SynthesizerService::class,
            'idea_forge' => [
                'driver' => BasicIdeaForgeDriver::class,
                'advisors' => [
                    [
                        'class' => OpenAIIdeaAdvisorDriver::class,
                        'weight' => 1.0,
                    ],
                ],
                'auditor' => BasicIdeaAuditorDriver::class,
                'picker' => BasicIdeaPickerDriver::class,
            ],
            'brief_builder' => [
                'driver' => BasicBriefBuilderDriver::class,
            ],
            'outline_builder' => [
                'driver' => BasicOutlineBuilderDriver::class,
            ],
            'author' => [
                'driver' => BasicAuthorDriver::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI idea advisor
    |--------------------------------------------------------------------------
    |
    | Used by {@see OpenAIIdeaAdvisorDriver} (synthesizer driver "openai").
    |
    */

    'openai_idea_advisor' => [
        'model' => env('SYNTHESIZER_OPENAI_IDEA_ADVISOR_MODEL', 'gpt-4o-mini'),
        'temperature' => 0.3,
        'max_temporal_suggestions' => 8,
        'max_intent_type_suggestions' => 8,
    ],
];
