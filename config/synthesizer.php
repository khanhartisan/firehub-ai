<?php

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
    | Orchestrator profiles wire subservices by short driver name. Each name is
    | resolved by that subservice's manager (see synthesizer.{subservice} below).
    |
    */

    'drivers' => [
        'basic' => [
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
            'writer' => 'basic',
            'illustration' => [
                'director' => 'basic',
                'illustrators' => ['basic'],
            ],
        ],

        'openai' => [
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
            'writer' => 'openai',
            'illustration' => [
                'director' => 'openai',
                'illustrators' => [
                    env('SYNTHESIZER_OPENAI_ILLUSTRATOR_DRIVER', 'openai'),
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subservice configuration
    |--------------------------------------------------------------------------
    |
    | Each subservice uses "default" plus "drivers" for implementation settings.
    | Class resolution lives in that subservice's *Manager.
    |
    */

    'idea_forge' => [
        'default' => 'basic',
        'drivers' => [
            'basic' => [],
        ],
    ],

    'idea_advisor' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_IDEA_ADVISOR_MODEL', 'gpt-4o-mini'),
                'temperature' => 1.2,
                'max_temporal_suggestions' => 8,
                'max_intent_type_suggestions' => 8,
            ],
        ],
    ],

    'idea_auditor' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_IDEA_AUDITOR_MODEL', 'gpt-4o-mini'),
                'temperature_uniqueness' => 0.1,
                'temperature_audit' => 0.3,
            ],
        ],
        'uniqueness' => [
            'vector_search_limit' => 20,
            'similarity_unique_cutoff' => 0.75,
            'similar_article_min_score' => 0.5,
        ],
    ],

    'idea_picker' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_IDEA_PICKER_MODEL', 'gpt-4o-mini'),
                'temperature' => 0.2,
            ],
        ],
    ],

    'researcher' => [
        'default' => 'openai',
        'max_pages' => (int) env('SYNTHESIZER_RESEARCH_MAX_PAGES', 20),
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_RESEARCHER_MODEL', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'max_points' => 8,
            ],
        ],
    ],

    'brief_builder' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_BRIEF_BUILDER_MODEL', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'max_instructions' => 6,
            ],
        ],
    ],

    'outline_builder' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_OUTLINE_BUILDER_MODEL', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'max_items' => 20,
                'max_depth' => 6,
            ],
        ],
    ],

    'editor' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_EDITOR_MODEL', 'gpt-4o-mini'),
                'temperature' => 0.2,
            ],
        ],
    ],

    'writer' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_WRITER_MODEL', env('SYNTHESIZER_OPENAI_AUTHOR_MODEL', 'gpt-4o-mini')),
                'temperature' => 0.5,
                'max_children' => 1000,
                'max_depth' => 20,
            ],
        ],
    ],

    'illustration_director' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_ILLUSTRATION_DIRECTOR_MODEL', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'max_contexts' => 8,
            ],
        ],
    ],

    'illustrator' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'identifier' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_IDENTIFIER', 'openai-illustrator'),
                'description' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_DESCRIPTION', 'OpenAI-backed illustration generator.'),
                'model' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_MODEL', 'gpt-image-1'),
                'quality' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_QUALITY', 'low'),
                'output_format' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_OUTPUT_FORMAT', 'png'),
                'count' => (int) env('SYNTHESIZER_OPENAI_ILLUSTRATOR_COUNT', 1),
                'filesystem_disk' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_FILESYSTEM_DISK', env('FILESYSTEM_DISK', 'local')),
                'filesystem_directory' => env('SYNTHESIZER_OPENAI_ILLUSTRATOR_FILESYSTEM_DIRECTORY', 'illustrations/generated'),
            ],
            'debug' => [
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
        ],
    ],
];
