<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Driver
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for each one. Here you may set the default queue driver.
    |
    | Supported: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'default' => env('QUEUE_DRIVER', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 9000,
        ],

        'achievement' => [
            'driver' => 'database',
            'table' => 'achievementjobs',
            'queue' => 'achievementjobs',
            'retry_after' => 90,
        ],

        'quest' => [
            'driver' => 'database',
            'table' => 'questjobs',
            'queue' => 'quest',
            'retry_after' => 90,
        ],

        'quest_autoclaim' => [
            'driver' => 'database',
            'table' => 'questjobs',
            'queue' => 'quest_autoclaim',
            'retry_after' => 90,
        ],

        'fraudqueue' => [
            'driver' => 'database',
            'table' => 'fraud_queue',
            'queue' => 'fraudqueue',
            'retry_after' => 90,
        ],

        'disbursequeue' => [
            'driver' => 'database',
            'table' => 'disburse_queue',
            'queue' => 'disbursequeue',
            'retry_after' => 90,
        ],

        'outletqueue' => [
            'driver' => 'database',
            'table' => 'outlet_queue',
            'queue' => 'outletqueue',
            'retry_after' => 90,
        ],

        'subscriptionqueue' => [
            'driver' => 'database',
            'table' => 'subscription_queue',
            'queue' => 'subscriptionqueue',
            'retry_after' => 90,
        ],

        'dealsqueue' => [
            'driver' => 'database',
            'table' => 'deals_queue',
            'queue' => 'dealsqueue',
            'retry_after' => 90,
        ],

        'validationpromopgqueue' => [
            'driver' => 'database',
            'table' => 'validation_promo_pg_queue',
            'queue' => 'validationpromopgqueue',
            'retry_after' => 90,
        ],

        'campaignqueue' => [
            'driver' => 'database',
            'table' => 'campaign_queue',
            'queue' => 'campaignqueue',
            'retry_after' => 9000,
        ],

        'export_franchise_queue' => [
            'driver' => 'database',
            'table' => 'export_franchise_jobs',
            'queue' => 'export_franchise_queue',
            'retry_after' => 90,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => 'your-public-key',
            'secret' => 'your-secret-key',
            'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
            'queue' => 'your-queue-name',
            'region' => 'us-east-1',
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
            'retry_after' => 90,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];
