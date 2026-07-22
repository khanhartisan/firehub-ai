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
    | Platform Manager Drivers
    |--------------------------------------------------------------------------
    |
    | Supported: "dummy"
    |
    */

    'platform_manager_drivers' => [

        'dummy' => [
            'reference_prefix' => env('HITL_PLATFORM_MANAGER_DUMMY_REFERENCE_PREFIX', 'dummy'),
        ],

        'firetasks' => [
            'base_url' => env('HITL_PLATFORM_MANAGER_FIRETASKS_BASE_URL'),
            'api_key' => env('HITL_PLATFORM_MANAGER_FIRETASKS_API_KEY'),
            'folder_id' => env('HITL_PLATFORM_MANAGER_FIRETASKS_FOLDER_ID'),
            'default_responsible_user_id' => env('HITL_PLATFORM_MANAGER_FIRETASKS_DEFAULT_RESPONSIBLE_USER_ID'),
            'note' => env('HITL_PLATFORM_MANAGER_FIRETASKS_NOTE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Agent Drivers
    |--------------------------------------------------------------------------
    |
    | Supported: "dummy", "openai", "openai_compatible"
    |
    */

    'task_agent_drivers' => [

        'dummy' => [
            'default_title' => env('HITL_TASK_AGENT_DUMMY_DEFAULT_TITLE', 'Untitled task'),
            'auto_action' => (bool) env('HITL_TASK_AGENT_DUMMY_AUTO_ACTION', true),
        ],

        'openai' => [
            'model' => env('HITL_TASK_AGENT_OPENAI_MODEL', 'gpt-5.4-mini'),
            'temperature' => (float) env('HITL_TASK_AGENT_OPENAI_TEMPERATURE', 0.2),
            'default_title' => env('HITL_TASK_AGENT_OPENAI_DEFAULT_TITLE', 'Untitled task'),
            'auto_action' => (bool) env('HITL_TASK_AGENT_OPENAI_AUTO_ACTION', true),
        ],

        'openai_compatible' => [
            'model' => env(
                'HITL_TASK_AGENT_OPENAI_COMPATIBLE_MODEL',
                env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')
            ),
            'temperature' => (float) env('HITL_TASK_AGENT_OPENAI_COMPATIBLE_TEMPERATURE', 0.2),
            'default_title' => env('HITL_TASK_AGENT_OPENAI_COMPATIBLE_DEFAULT_TITLE', 'Untitled task'),
            'auto_action' => (bool) env('HITL_TASK_AGENT_OPENAI_COMPATIBLE_AUTO_ACTION', true),
        ],

    ],

];
