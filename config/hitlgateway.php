<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default HitlGateway Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default HitlGateway driver used by the application.
    |
    */

    'default' => env('HITL_GATEWAY_DRIVER', 'dummy'),

    /*
    |--------------------------------------------------------------------------
    | HitlGateway Drivers
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "dummy"
    |
    */

    'drivers' => [

        'dummy' => [
            'reference_prefix' => env('HITL_GATEWAY_DUMMY_REFERENCE_PREFIX', 'dummy'),
            'default_title' => env('HITL_GATEWAY_DUMMY_DEFAULT_TITLE', 'Untitled task'),
            'auto_action' => (bool) env('HITL_GATEWAY_DUMMY_AUTO_ACTION', true),
        ],

    ],

];
