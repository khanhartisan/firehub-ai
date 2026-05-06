<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Semantic Context Builder Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default conversational semantic context builder
    | driver used when no explicit driver is selected.
    |
    */

    'default' => env('SEMANTIC_CONTEXT_BUILDER_DRIVER', 'dummy'),

    /*
    |--------------------------------------------------------------------------
    | Semantic Context Builder Drivers
    |--------------------------------------------------------------------------
    |
    | Configure available conversational semantic context builder drivers.
    |
    */

    'drivers' => [
        'dummy' => [],
        'openai' => [
            'model' => env('SEMANTIC_CONTEXT_BUILDER_OPENAI_MODEL', 'gpt-5.4'),
            'temperature' => (float) env('SEMANTIC_CONTEXT_BUILDER_OPENAI_TEMPERATURE', 0.2),
        ],
    ],
];
