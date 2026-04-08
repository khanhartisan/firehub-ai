<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Size Limits (per queue)
    |--------------------------------------------------------------------------
    |
    | Maximum number of jobs allowed per queue. When a queue reaches its limit,
    | no new jobs are dispatched until it drains. Default when not set: 10000.
    |
    | - max_default_queue_size: default queue (default: 10000)
    | - max_scraping_queue_size: scraping queue (default: 1000)
    | - max_scheduler_queue_size: scheduler queue (default: 100)
    |
    */

    'max_default_queue_size' => env('QUEUE_DEFAULT_MAX_SIZE') !== null ? (int) env('QUEUE_DEFAULT_MAX_SIZE') : 10,
    'max_page_scraping_queue_size' => (int) env('QUEUE_SCRAPING_MAX_SIZE', 10),
    'max_file_scraping_queue_size' => (int) env('QUEUE_SCRAPING_MAX_SIZE', 10),
    'max_scheduler_queue_size' => (int) env('QUEUE_SCHEDULER_MAX_SIZE', 10),

    /*
    |--------------------------------------------------------------------------
    | Max Scrape Attempts
    |--------------------------------------------------------------------------
    |
    | After this many failed scrape attempts, the page is no longer queued.
    | Set via SCRAPE_MAX_ATTEMPTS (default: 5).
    |
    */

    'max_scrape_attempts' => (int) env('SCRAPE_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | ScrapeSourcesJob: Chunk Size & Timeout
    |--------------------------------------------------------------------------
    |
    | Chunk size for iterating sources (millions-scale). Timeout in seconds;
    | job stops processing further chunks after this many seconds.
    | SCRAPE_SOURCES_CHUNK_SIZE (default: 500), SCRAPE_SOURCES_MAX_SECONDS (default: 300).
    |
    */

    'scrape_sources_chunk_size' => (int) env('SCRAPE_SOURCES_CHUNK_SIZE', 500),
    'scrape_sources_max_seconds' => (int) env('SCRAPE_SOURCES_MAX_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
