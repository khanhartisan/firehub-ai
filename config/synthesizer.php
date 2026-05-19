<?php

use App\Services\Synthesizer\Writer\Drivers\BasicWriterDriver;
use App\Services\Synthesizer\Writer\Drivers\OpenAIWriterDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAIBriefBuilderDriver;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaExpansionAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\OpenAIIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\OpenAIIdeaPickerDriver;
use App\Services\Synthesizer\Illustration\Director\Drivers\BasicDirectorDriver;
use App\Services\Synthesizer\Illustration\Director\Drivers\OpenAIDirectorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\BasicIllustratorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\OpenAIIllustratorDriver;
use App\Services\Synthesizer\Editor\Drivers\BasicEditorDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\OpenAIOutlineBuilderDriver;
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
            'service' => [
                'driver' => SynthesizerService::class,
            ],
            'idea_forge' => [
                'driver' => BasicIdeaForgeDriver::class,
                /*
                | Idea advisors: each entry is ['driver' => SomeIdeaAdvisor::class, 'weight' => 2.0]
                | Weight is optional (defaults to 1 on the advisor). Relative weights affect
                | top suggestion selection in the article idea stage.
                */
                'advisors' => [
                    [
                        'driver' => BasicIdeaAdvisorDriver::class,
                        'weight' => 1.0,
                    ],
                ],
                'auditor' => [
                    'driver' => BasicIdeaAuditorDriver::class,
                ],
                'picker' => [
                    'driver' => BasicIdeaPickerDriver::class,
                ],
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
            'editor' => [
                'driver' => BasicEditorDriver::class,
            ],
            'author' => [
                'driver' => BasicWriterDriver::class,
            ],
            'illustration' => [
                'director' => [
                    'driver' => BasicDirectorDriver::class,
                ],
                'illustrators' => [
                    ['driver' => BasicIllustratorDriver::class],
                ],
            ],
        ],

        'openai' => [
            'service' => [
                'driver' => SynthesizerService::class,
            ],
            'idea_forge' => [
                'driver' => BasicIdeaForgeDriver::class,
                'advisors' => [
                    [
                        'driver' => OpenAIIdeaAdvisorDriver::class,
                        'weight' => 1.0,
                    ],
                    [
                        'driver' => OpenAIIdeaExpansionAdvisorDriver::class,
                        'weight' => 1.0,
                    ],
                ],
                'auditor' => [
                    'driver' => OpenAIIdeaAuditorDriver::class,
                ],
                'picker' => [
                    'driver' => OpenAIIdeaPickerDriver::class,
                ],
            ],
            'researcher' => [
                'driver' => OpenAIResearcherDriver::class,
            ],
            'brief_builder' => [
                'driver' => OpenAIBriefBuilderDriver::class,
            ],
            'outline_builder' => [
                'driver' => OpenAIOutlineBuilderDriver::class,
            ],
            'editor' => [
                'driver' => BasicEditorDriver::class,
            ],
            'author' => [
                'driver' => OpenAIWriterDriver::class,
            ],
            'illustration' => [
                'director' => [
                    'driver' => OpenAIDirectorDriver::class,
                ],
                'illustrators' => [
                    [
                        'driver' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_DRIVER', OpenAIIllustratorDriver::class),
                    ],
                ],
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
        'temperature' => 1.2,
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
    | OpenAI brief builder
    |--------------------------------------------------------------------------
    |
    | Used by {@see OpenAIBriefBuilderDriver} (synthesizer driver "openai").
    |
    */

    'openai_brief_builder' => [
        'model' => env('SYNTHESIZER_OPENAI_BRIEF_BUILDER_MODEL', 'gpt-4o-mini'),
        'temperature' => 0.2,
        'max_instructions' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI outline builder
    |--------------------------------------------------------------------------
    |
    | Used by {@see OpenAIOutlineBuilderDriver} (synthesizer driver "openai").
    |
    */

    'openai_outline_builder' => [
        'model' => env('SYNTHESIZER_OPENAI_OUTLINE_BUILDER_MODEL', 'gpt-4o-mini'),
        'temperature' => 0.2,
        'max_items' => 20,
        'max_depth' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI author
    |--------------------------------------------------------------------------
    |
    | Used by {@see OpenAIWriterDriver} (synthesizer driver "openai").
    |
    */

    'openai_author' => [
        'model' => env('SYNTHESIZER_OPENAI_AUTHOR_MODEL', 'gpt-4o-mini'),
        'temperature' => 0.5,
        'max_children' => 1000,
        'max_depth' => 20,
    ],

    'openai_illustration_director' => [
        'model' => env('SYNTHESIZER_OPENAI_ILLUSTRATION_DIRECTOR_MODEL', 'gpt-4o-mini'),
        'temperature' => 0.2,
        'max_contexts' => 8,
    ],

    'openai_illustrator' => [
        'identifier' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_IDENTIFIER', 'openai-illustrator'),
        'description' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_DESCRIPTION', 'OpenAI-backed illustration generator.'),
        'model' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_MODEL', 'gpt-image-1'),
        'quality' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_QUALITY', 'low'),
        'output_format' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_OUTPUT_FORMAT', 'png'),
        'count' => (int) env('SYNTHESIZER_OPENAI_ILLUSTRATOR_COUNT', 1),
        'filesystem_disk' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_FILESYSTEM_DISK', env('FILESYSTEM_DISK', 'local')),
        'filesystem_directory' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_FILESYSTEM_DIRECTORY', 'illustrations/generated'),
    ],

    'openai_debug_illustrator' => [
        'identifier' => env('SYNTHESIZER_OPENAI_DEBUG_ILLUSTRATOR_IDENTIFIER', 'openai-debug-illustrator'),
        'description' => env('SYNTHESIZER_OPENAI_DEBUG_ILLUSTRATOR_DESCRIPTION', 'OpenAI prompt logger with dummy image output for development.'),
        'model' => env('SYNTHESIZER_OPENAI_DEBUG_ILLUSTRATOR_MODEL', 'gpt-image-1'),
        'quality' => env('SYNTHESIZER_OPENAI_DEBUG_ILLUSTRATOR_QUALITY', 'low'),
        'output_format' => env('SYNTHESIZER_OPENAI_DEBUG_ILLUSTRATOR_OUTPUT_FORMAT', 'png'),
        'count' => 1,
        'filesystem_disk' => env('SYNTHESIZER_OPENAI_DEBUG_ILLUSTRATOR_FILESYSTEM_DISK', env('FILESYSTEM_DISK', 'local')),
        'filesystem_directory' => env('SYNTHESIZER_OPENAI_DEBUG_ILLUSTRATOR_FILESYSTEM_DIRECTORY', 'illustrations/generated'),
        'debug_log_path' => env('SYNTHESIZER_OPENAI_DEBUG_ILLUSTRATOR_LOG_PATH', storage_path('logs/openai-illustrator-debug.log')),
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
