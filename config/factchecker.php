<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default FactChecker Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default FactChecker driver that will be used
    | whenever no explicit driver is selected.
    |
    */

    'default' => env('FACTCHECKER_DRIVER', 'basic'),

    /*
    |--------------------------------------------------------------------------
    | FactChecker Drivers
    |--------------------------------------------------------------------------
    |
    | Configure each available fact-checking driver here.
    |
    | Supported drivers: "basic", "openai", "openai_compatible"
    |
    */

    'drivers' => [

        'basic' => [
            'min_confidence' => (float) env('FACTCHECKER_BASIC_MIN_CONFIDENCE', 0.6),
        ],

        'openai' => [
            'model' => env('FACTCHECKER_OPENAI_MODEL', 'gpt-4o-mini'),
        ],

        'openai_compatible' => [
            'model' => env('FACTCHECKER_OPENAI_COMPATIBLE_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-4o-mini')),
        ],

    ],

];
