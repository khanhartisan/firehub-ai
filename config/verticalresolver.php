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

    'default' => env('VERTICALRESOLVER_DRIVER', 'openai_compatible'),

    /*
    |--------------------------------------------------------------------------
    | VerticalResolver Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every VerticalResolver
    | driver used by your application. An example configuration is provided
    | for each driver supported. You're also free to add more drivers.
    |
    | Supported drivers: "openai", "keyword", "openai_compatible"
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

        'openai_compatible' => [
            'model' => env('VERTICALRESOLVER_OPENAI_COMPATIBLE_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-4o-mini')),
            'max_content_length' => (int) env('VERTICALRESOLVER_OPENAI_COMPATIBLE_MAX_CONTENT_LENGTH', env('VERTICALRESOLVER_MAX_CONTENT_LENGTH', 50000)),
        ],

    ],

];
