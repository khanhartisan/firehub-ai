<?php

use App\Services\Synthesizer\Support\SynthesizerDriverProfiles;

$openaiCompatibleConnection = [
    'api_key' => env('SYNTHESIZER_OPENAI_COMPATIBLE_API_KEY', env('OPENAI_COMPATIBLE_API_KEY')),
    'base_url' => env('SYNTHESIZER_OPENAI_COMPATIBLE_BASE_URL', env('OPENAI_COMPATIBLE_BASE_URL', 'https://api.openai.com/v1/')),
    'timeout' => (int) env('SYNTHESIZER_OPENAI_COMPATIBLE_TIMEOUT', env('OPENAI_COMPATIBLE_TIMEOUT', 120)),
    'structured_output' => env('SYNTHESIZER_OPENAI_COMPATIBLE_STRUCTURED_OUTPUT', 'json_schema'),
];

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
    | Article rectification (build pipeline)
    |--------------------------------------------------------------------------
    |
    | Default max_rectification_rounds per critic when a profile critics[] entry omits it.
    |
    */

    'max_rectification_rounds' => (int) env('SYNTHESIZER_MAX_RECTIFICATION_ROUNDS', 2),

    /*
    |--------------------------------------------------------------------------
    | Synthesizer Drivers
    |--------------------------------------------------------------------------
    |
    | Orchestrator profiles wire subservices by short driver name. Each name is
    | resolved by that subservice's manager (see synthesizer.{subservice} below).
    |
    | critics[] entries: driver, purpose, optional order (used by RECTIFICATION stage_data),
    | optional max_rectification_rounds (int|null, highest value wins for the driver),
    | optional min_confidence / min_importance (0–1 per entry; default 0.8 / 0.7 when omitted).
    |
    */

    'drivers' => [
        'basic' => SynthesizerDriverProfiles::basic(),
        'openai' => SynthesizerDriverProfiles::openai(),
        'openai_compatible' => SynthesizerDriverProfiles::openaiCompatible(),
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
                'model' => env('SYNTHESIZER_OPENAI_IDEA_ADVISOR_MODEL', 'gpt-5.4-mini'),
                'temperature' => 1.2,
                'max_temporal_suggestions' => 8,
                'max_intent_type_suggestions' => 8,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_IDEA_ADVISOR_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => (float) env('SYNTHESIZER_OPENAI_COMPATIBLE_IDEA_ADVISOR_TEMPERATURE', 1.2),
                'max_temporal_suggestions' => 8,
                'max_intent_type_suggestions' => 8,
            ]),
        ],
    ],

    'idea_auditor' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_IDEA_AUDITOR_MODEL', 'gpt-5.4-mini'),
                'temperature_uniqueness' => 0.1,
                'temperature_audit' => 0.3,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_IDEA_AUDITOR_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature_uniqueness' => 0.1,
                'temperature_audit' => 0.3,
            ]),
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
                'model' => env('SYNTHESIZER_OPENAI_IDEA_PICKER_MODEL', 'gpt-5.4-mini'),
                'temperature' => 0.2,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_IDEA_PICKER_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.2,
            ]),
        ],
    ],

    'researcher' => [
        'default' => 'openai',
        'max_pages' => (int) env('SYNTHESIZER_RESEARCH_MAX_PAGES', 20),
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_RESEARCHER_MODEL', 'gpt-5.4-mini'),
                'temperature' => 0.2,
                'max_points' => 16,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_RESEARCHER_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.2,
                'max_points' => 16,
            ]),
        ],
    ],

    'brief_builder' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_BRIEF_BUILDER_MODEL', 'gpt-5.4-mini'),
                'temperature' => 0.2,
                'max_instructions' => 6,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_BRIEF_BUILDER_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.2,
                'max_instructions' => 6,
            ]),
        ],
    ],

    'outline_builder' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_OUTLINE_BUILDER_MODEL', 'gpt-5.4-mini'),
                'temperature' => 0.2,
                'max_items' => 20,
                'max_depth' => 6,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_OUTLINE_BUILDER_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.2,
                'max_items' => 20,
                'max_depth' => 6,
            ]),
        ],
    ],

    'editor' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_EDITOR_MODEL', 'gpt-5.4-mini'),
                'temperature' => 0.2,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_EDITOR_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.2,
            ]),
        ],
    ],

    'critic' => [
        'default' => 'openai',
        'drivers' => [
            'basic' => [],
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_CRITIC_MODEL', 'gpt-5.4-mini'),
                'temperature' => 0.2,
                'max_criticisms_per_critic' => 20,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_CRITIC_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.2,
                'max_criticisms_per_critic' => 20,
            ]),
        ],
    ],

    'writer' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_WRITER_MODEL', env('SYNTHESIZER_OPENAI_AUTHOR_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.5,
                'max_children' => 1000,
                'max_depth' => 20,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_WRITER_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.5,
                'max_children' => 1000,
                'max_depth' => 20,
            ]),
        ],
    ],

    'tagger' => [
        'default' => 'basic',
        'drivers' => [
            'basic' => [],
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_TAGGER_MODEL', 'gpt-5.4-mini'),
                'temperature' => 0.1,
                'max_tags' => 8,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_TAGGER_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.1,
                'max_tags' => 8,
            ]),
        ],
    ],

    'illustration_director' => [
        'default' => 'openai',
        'drivers' => [
            'openai' => [
                'model' => env('SYNTHESIZER_OPENAI_ILLUSTRATION_DIRECTOR_MODEL', 'gpt-5.4-mini'),
                'temperature' => 0.2,
                'max_contexts' => 8,
            ],
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATION_DIRECTOR_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
                'temperature' => 0.2,
                'max_contexts' => 8,
            ]),
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
            'openai_compatible' => array_merge($openaiCompatibleConnection, [
                'base_url' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_BASE_URL'),
                'identifier' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_IDENTIFIER', 'openai-compatible-illustrator'),
                'description' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_DESCRIPTION', 'OpenAI-compatible illustration generator.'),
                'model' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-image-1')),
                'quality' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_QUALITY', 'low'),
                'output_format' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_OUTPUT_FORMAT', 'png'),
                'count' => (int) env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_COUNT', 1),
                'filesystem_disk' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_FILESYSTEM_DISK', env('FILESYSTEM_DISK', 'local')),
                'filesystem_directory' => env('SYNTHESIZER_OPENAI_COMPATIBLE_ILLUSTRATOR_FILESYSTEM_DIRECTORY', 'illustrations/generated'),
            ]),
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
