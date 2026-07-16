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
    | Supported platform_manager drivers: "dummy"
    | Supported task_agent drivers: "dummy", "openai", "openai_compatible"
    |
    */

    'drivers' => [

        'dummy' => [
            'reference_prefix' => env('HITL_GATEWAY_DUMMY_REFERENCE_PREFIX', 'dummy'),
            'default_title' => env('HITL_GATEWAY_DUMMY_DEFAULT_TITLE', 'Untitled task'),
            'auto_action' => (bool) env('HITL_GATEWAY_DUMMY_AUTO_ACTION', true),
        ],

        'openai' => [
            'model' => env('HITL_TASK_AGENT_OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => (float) env('HITL_TASK_AGENT_OPENAI_TEMPERATURE', 0.2),
            'default_title' => env('HITL_TASK_AGENT_OPENAI_DEFAULT_TITLE', 'Untitled task'),
            'auto_action' => (bool) env('HITL_TASK_AGENT_OPENAI_AUTO_ACTION', true),
        ],

        'openai_compatible' => [
            'model' => env(
                'HITL_TASK_AGENT_OPENAI_COMPATIBLE_MODEL',
                env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-4o-mini')
            ),
            'temperature' => (float) env('HITL_TASK_AGENT_OPENAI_COMPATIBLE_TEMPERATURE', 0.2),
            'default_title' => env('HITL_TASK_AGENT_OPENAI_COMPATIBLE_DEFAULT_TITLE', 'Untitled task'),
            'auto_action' => (bool) env('HITL_TASK_AGENT_OPENAI_COMPATIBLE_AUTO_ACTION', true),
        ],

    ],

];
