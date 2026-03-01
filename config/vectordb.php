<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default VectorDB Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default VectorDB driver that will be used for
    | semantic search and similarity lookups. The driver specified here will
    | be used when no explicit driver is specified.
    |
    */

    'default' => env('VECTORDB_DRIVER', 'pgvector'),

    /*
    |--------------------------------------------------------------------------
    | VectorDB Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every VectorDB driver
    | used by your application. Supported drivers: "pgvector"
    |
    */

    'drivers' => [

        'pgvector' => [
            'connection' => env('VECTORDB_PGVECTOR_CONNECTION', 'pgsql'),
            'default_dimension' => (int) env('VECTORDB_PGVECTOR_DEFAULT_DIMENSION', 1536),
        ],

    ],

];
