<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default IntentResolver Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default IntentResolver driver used to infer
    | search intent from content and to suggest keywords.
    |
    */

    'default' => env('INTENTRESOLVER_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | IntentResolver Drivers
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "openai", "gemma3"
    |
    */

    'drivers' => [

        'openai' => [
            'model' => env('INTENTRESOLVER_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_content_length' => (int) env('INTENTRESOLVER_MAX_CONTENT_LENGTH', 50000),
            'max_keywords' => (int) env('INTENTRESOLVER_MAX_KEYWORDS', 25),
            'max_resolve_intents' => (int) env('INTENTRESOLVER_MAX_RESOLVE_INTENTS', 8),
        ],

        'gemma3' => [
            'model' => env('INTENTRESOLVER_GEMMA3_MODEL', env('GEMMA3_DEFAULT_MODEL', 'gemma-3-27b-it')),
            'max_content_length' => (int) env('INTENTRESOLVER_GEMMA3_MAX_CONTENT_LENGTH', env('INTENTRESOLVER_MAX_CONTENT_LENGTH', 50000)),
            'max_keywords' => (int) env('INTENTRESOLVER_GEMMA3_MAX_KEYWORDS', env('INTENTRESOLVER_MAX_KEYWORDS', 25)),
            'max_resolve_intents' => (int) env('INTENTRESOLVER_GEMMA3_MAX_RESOLVE_INTENTS', env('INTENTRESOLVER_MAX_RESOLVE_INTENTS', 8)),
        ],

    ],

];
