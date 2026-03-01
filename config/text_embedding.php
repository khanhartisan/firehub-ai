<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Text Embedding Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default text embedding driver (e.g. openai).
    | The driver is used to convert text to vectors for VectorDB indexing
    | and semantic search. Uses Laravel AI SDK under the hood.
    |
    */

    'default' => env('TEXT_EMBEDDING_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Text Embedding Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure each text embedding driver. All drivers use the
    | Laravel AI SDK; only provider, model, and dimension differ per driver.
    |
    */

    'drivers' => [

        'openai' => [
            'provider' => env('TEXT_EMBEDDING_OPENAI_PROVIDER', null),
            'model' => env('TEXT_EMBEDDING_OPENAI_MODEL', 'text-embedding-3-small'),
            'dimension' => (int) env('TEXT_EMBEDDING_OPENAI_DIMENSION', 1536),
        ],

        'azure' => [
            'provider' => env('TEXT_EMBEDDING_AZURE_PROVIDER', 'azure'),
            'model' => env('TEXT_EMBEDDING_AZURE_MODEL', 'text-embedding-3-small'),
            'dimension' => (int) env('TEXT_EMBEDDING_AZURE_DIMENSION', 1536),
        ],

    ],

];
