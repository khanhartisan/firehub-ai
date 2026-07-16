<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hitl Platform Manager
    |--------------------------------------------------------------------------
    */

    'platform_manager' => env('HITL_PLATFORM_MANAGER_DRIVER', 'dummy'),

    /*
    |--------------------------------------------------------------------------
    | Hitl Task Agent
    |--------------------------------------------------------------------------
    */

    'task_agent' => env('HITL_TASK_AGENT_DRIVER', 'dummy'),

    /*
    |--------------------------------------------------------------------------
    | Driver Config
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
