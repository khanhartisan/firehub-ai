<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default OpenAI Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default OpenAI-compatible API driver that will
    | be used by the application. The driver specified here will be used when
    | no explicit driver is specified when making API requests.
    |
    */

    'default' => env('OPENAI_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every OpenAI-compatible
    | API driver used by your application. An example configuration is provided
    | for each driver supported. You're also free to add more drivers.
    |
    | Supported drivers: "openai" (Responses API), "openai_compatible" (Chat Completions API)
    |
    */

    'drivers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1/'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),
            'timeout' => env('OPENAI_TIMEOUT', 60),
            'beta_header' => env('OPENAI_BETA_HEADER', 'responses=v1'),
        ],

        'openai_compatible' => [
            'api_key' => env('OPENAI_COMPATIBLE_API_KEY'),
            'base_url' => env('OPENAI_COMPATIBLE_BASE_URL', 'https://api.openai.com/v1/'),
            'default_model' => env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini'),
            'timeout' => (int) env('OPENAI_COMPATIBLE_TIMEOUT', 60),
            'beta_header' => env('OPENAI_COMPATIBLE_BETA_HEADER'),
        ],

    ],

];
