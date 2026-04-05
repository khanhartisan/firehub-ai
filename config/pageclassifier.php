<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default PageClassifier Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default PageClassifier driver that will be used
    | by the application. The driver specified here will be used when no
    | explicit driver is specified when classifying pages.
    |
    */

    'default' => env('PAGECLASSIFIER_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | PageClassifier Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every PageClassifier
    | driver used by your application. An example configuration is provided
    | for each driver supported. You're also free to add more drivers.
    |
    | Supported drivers: "openai", "gemma3"
    |
    */

    'drivers' => [

        'openai' => [
            'model' => env('PAGECLASSIFIER_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_html_length' => env('PAGECLASSIFIER_MAX_HTML_LENGTH', 100000),
        ],

        'gemma3' => [
            'model' => env('PAGECLASSIFIER_GEMMA3_MODEL', env('GEMMA3_DEFAULT_MODEL', 'gemma-3-27b-it')),
            'max_html_length' => env('PAGECLASSIFIER_GEMMA3_MAX_HTML_LENGTH', env('PAGECLASSIFIER_MAX_HTML_LENGTH', 100000)),
        ],

    ],

];
