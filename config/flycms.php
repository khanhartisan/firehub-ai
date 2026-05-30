<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default FlyCMS Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default FlyCMS driver that will be used by the
    | application. The driver specified here will be used when no explicit
    | driver is selected.
    |
    */

    'default' => env('FLYCMS_DRIVER', 'pseudo'),

    /*
    |--------------------------------------------------------------------------
    | FlyCMS Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every FlyCMS driver
    | used by your application. You're also free to add more drivers.
    |
    | Supported drivers: "pseudo"
    |
    */

    'drivers' => [

        'pseudo' => [
            'base_url' => env('FLYCMS_PSEUDO_BASE_URL', 'https://flycms.test'),
            'api_key' => env('FLYCMS_PSEUDO_API_KEY', 'pseudo-api-key'),
        ],

    ],

];
