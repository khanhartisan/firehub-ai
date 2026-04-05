<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default VerticalResolver Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default VerticalResolver driver that will be used
    | by the application. The driver specified here will be used when no
    | explicit driver is specified when resolving verticals from content.
    |
    */

    'default' => env('VERTICALRESOLVER_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | VerticalResolver Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every VerticalResolver
    | driver used by your application. An example configuration is provided
    | for each driver supported. You're also free to add more drivers.
    |
    | Supported drivers: "openai", "keyword", "gemma3"
    |
    */

    'drivers' => [

        'openai' => [
            'model' => env('VERTICALRESOLVER_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_content_length' => (int) env('VERTICALRESOLVER_MAX_CONTENT_LENGTH', 50000),
        ],

        'keyword' => [
            'match_threshold' => (float) env('VERTICALRESOLVER_MATCH_THRESHOLD', 0.4),
            'proposal_threshold' => (float) env('VERTICALRESOLVER_PROPOSAL_THRESHOLD', 0.15),
            'max_content_length' => (int) env('VERTICALRESOLVER_MAX_CONTENT_LENGTH', 50000),
        ],

        'gemma3' => [
            'model' => env('VERTICALRESOLVER_GEMMA3_MODEL', env('GEMMA3_DEFAULT_MODEL', 'gemma-3-27b-it')),
            'max_content_length' => (int) env('VERTICALRESOLVER_GEMMA3_MAX_CONTENT_LENGTH', env('VERTICALRESOLVER_MAX_CONTENT_LENGTH', 50000)),
        ],

    ],

];
