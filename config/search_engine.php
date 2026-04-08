<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default search engine driver used for web / SERP
    | queries. You may change this when switching providers or for tests.
    |
    */

    'default' => env('SEARCH_ENGINE_DRIVER', 'google'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Third-party backends (HTTP APIs, scraping services, etc.). Credentials and
    | connection defaults live here. Multiple logical drivers may use the same
    | provider or different ones.
    |
    */

    'providers' => [

        'searchapi' => [
            'api_key' => env('SEARCHAPI_API_KEY'),
            'base_url' => env('SEARCHAPI_BASE_URL', 'https://www.searchapi.io'),
            'timeout' => (int) env('SEARCHAPI_TIMEOUT', 90),
            'connect_timeout' => (int) env('SEARCHAPI_CONNECT_TIMEOUT', 15),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Search Engine Drivers
    |--------------------------------------------------------------------------
    |
    | Logical engines (e.g. "google"). Set "provider" to a key from "providers"
    | for implementations that delegate to a backend. Add other keys here only
    | for options that are specific to this driver, not to a provider.
    |
    */

    'drivers' => [

        'google' => [
            'provider' => env('SEARCH_ENGINE_GOOGLE_PROVIDER', 'searchapi'),
        ],

    ],

];
