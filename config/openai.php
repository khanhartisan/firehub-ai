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
    | Supported drivers: "openai", "grok", "gemma3"
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

        'grok' => [
            'api_key' => env('GROK_API_KEY'),
            'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1/'),
            'default_model' => env('GROK_DEFAULT_MODEL', 'grok-beta'),
            'timeout' => env('GROK_TIMEOUT', 60),
            'beta_header' => env('GROK_BETA_HEADER', null),
        ],

        'gemma3' => [
            'api_key' => env('GEMMA3_API_KEY', env('GEMINI_API_KEY')),
            'base_url' => env('GEMMA3_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai/'),
            'default_model' => env('GEMMA3_DEFAULT_MODEL', 'gemma-3-27b-it'),
            'timeout' => env('GEMMA3_TIMEOUT', 60),
            'beta_header' => env('GEMMA3_BETA_HEADER', null),
        ],

    ],

];
