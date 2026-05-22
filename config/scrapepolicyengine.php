<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default ScrapePolicyEngine Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default ScrapePolicyEngine driver that will be used
    | by the application. The driver specified here will be used when no
    | explicit driver is specified when evaluating scraping policies.
    |
    */

    'default' => env('SCRAPE_POLICY_ENGINE_DRIVER', 'openai_compatible'),

    /*
    |--------------------------------------------------------------------------
    | ScrapePolicyEngine Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every ScrapePolicyEngine
    | driver used by your application. An example configuration is provided
    | for each driver supported. You're also free to add more drivers.
    |
    | Supported drivers: "dummy", "openai", "openai_compatible"
    |
    */

    'drivers' => [

        'dummy' => [
            'default_interval_hours' => env('SCRAPE_POLICY_ENGINE_DUMMY_INTERVAL_HOURS', 24),
        ],

        'openai' => [
            'model' => env('SCRAPE_POLICY_ENGINE_OPENAI_MODEL', 'gpt-4o-mini'),
        ],

        'openai_compatible' => [
            'model' => env('SCRAPE_POLICY_ENGINE_OPENAI_COMPATIBLE_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-4o-mini')),
        ],

    ],

];
