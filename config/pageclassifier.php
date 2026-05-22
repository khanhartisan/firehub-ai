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

    'default' => env('PAGECLASSIFIER_DRIVER', 'openai_compatible'),

    /*
    |--------------------------------------------------------------------------
    | PageClassifier Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every PageClassifier
    | driver used by your application. An example configuration is provided
    | for each driver supported. You're also free to add more drivers.
    |
    | Supported drivers: "openai", "openai_compatible"
    |
    */

    'drivers' => [

        'openai' => [
            'model' => env('PAGECLASSIFIER_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_html_length' => env('PAGECLASSIFIER_MAX_HTML_LENGTH', 100000),
        ],

        'openai_compatible' => [
            'model' => env('PAGECLASSIFIER_OPENAI_COMPATIBLE_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-4o-mini')),
            'max_html_length' => (int) env('PAGECLASSIFIER_OPENAI_COMPATIBLE_MAX_HTML_LENGTH', env('PAGECLASSIFIER_MAX_HTML_LENGTH', 100000)),
        ],

    ],

];
