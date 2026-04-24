<?php

use App\Services\Synthesizer\Author\Drivers\BasicAuthorDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\OpenAIIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\OpenAIIdeaPickerDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\Researcher\Drivers\BasicResearcherDriver;
use App\Services\Synthesizer\Researcher\Drivers\OpenAIResearcherDriver;
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
            'researcher' => [
                'driver' => BasicResearcherDriver::class,
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
                'auditor' => OpenAIIdeaAuditorDriver::class,
                'picker' => OpenAIIdeaPickerDriver::class,
            ],
            'researcher' => [
                'driver' => OpenAIResearcherDriver::class,
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

    /*
    |--------------------------------------------------------------------------
    | OpenAI idea auditor
    |--------------------------------------------------------------------------
    |
    | Used by {@see OpenAIIdeaAuditorDriver} (synthesizer driver "openai").
    |
    */

    'openai_idea_auditor' => [
        'model' => env('SYNTHESIZER_OPENAI_IDEA_AUDITOR_MODEL', 'gpt-4o-mini'),
        'temperature_uniqueness' => 0.1,
        'temperature_audit' => 0.3,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI idea picker
    |--------------------------------------------------------------------------
    |
    | Used by {@see OpenAIIdeaPickerDriver} (synthesizer driver "openai").
    |
    */

    'openai_idea_picker' => [
        'model' => env('SYNTHESIZER_OPENAI_IDEA_PICKER_MODEL', 'gpt-4o-mini'),
        'temperature' => 0.2,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI researcher
    |--------------------------------------------------------------------------
    |
    | Used by {@see OpenAIResearcherDriver} (synthesizer driver "openai").
    |
    */

    'openai_researcher' => [
        'model' => env('SYNTHESIZER_OPENAI_RESEARCHER_MODEL', 'gpt-4o-mini'),
        'temperature' => 0.2,
        'max_points' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Research stage extraction
    |--------------------------------------------------------------------------
    |
    | Controls how many source pages are considered for point extraction during
    | the article research stage. Pages are prioritized by best (lowest)
    | keyword ranking position first.
    |
    */
    'research' => [
        'max_pages' => (int) env('SYNTHESIZER_RESEARCH_MAX_PAGES', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idea uniqueness (vector similarity)
    |--------------------------------------------------------------------------
    |
    | Used by {@see OpenAIIdeaAuditorDriver} for vector retrieval and scoring
    | (limits / thresholds). {@see BasicIdeaAuditorDriver} does not use vector search;
    | it returns a fixed stub report for tests and local wiring.
    |
    */

    'idea_uniqueness' => [
        'vector_search_limit' => 20,
        'similarity_unique_cutoff' => 0.75,
        'similar_article_min_score' => 0.5,
    ],
];
