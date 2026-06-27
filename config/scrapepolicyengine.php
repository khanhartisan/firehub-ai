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
    | Priority Backlog Defer Minutes
    |--------------------------------------------------------------------------
    |
    | When budget-respecting pages would scrape immediately but priority pages
    | (ignore_scraping_budget) exceed available page_scraping queue slots, each
    | excess page adds this many minutes to the initial scrape time.
    |
    */

    'priority_backlog_defer_minutes' => (int) env('SCRAPE_POLICY_ENGINE_PRIORITY_BACKLOG_DEFER_MINUTES', 360),

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
            'model' => env('SCRAPE_POLICY_ENGINE_OPENAI_MODEL', 'gpt-5.4-mini'),
        ],

        'openai_compatible' => [
            'model' => env('SCRAPE_POLICY_ENGINE_OPENAI_COMPATIBLE_MODEL', env('OPENAI_COMPATIBLE_DEFAULT_MODEL', 'gpt-5.4-mini')),
        ],

    ],

];
