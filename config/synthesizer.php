<?php

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
    | Synthesizer Drivers
    |--------------------------------------------------------------------------
    |
    | Configure available synthesizer drivers. The "basic" driver is a local,
    | deterministic implementation suitable for development and tests.
    |
    */

    'drivers' => [
        'basic' => [],
    ],
];
