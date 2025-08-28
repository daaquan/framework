<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Environment Detection
    |--------------------------------------------------------------------------
    |
    | This configuration determines the environment detection rules used by
    | the framework to determine the current application environment based
    | on hostname patterns or other criteria.
    |
    */

    'environments' => [
        'local' => [
            '*.local',
            'localhost',
            '127.0.0.1',
            '::1',
        ],

        'development' => [
            '*.dev',
            'dev-*',
            '*-dev.*',
        ],

        'staging' => [
            '*.staging',
            'staging-*',
            '*-staging.*',
            'stage-*',
            '*.stage',
        ],

        'testing' => [
            '*.test',
            'test-*',
            '*-test.*',
        ],

        'production' => [
            // Production hostnames are typically not wildcarded
            // Add specific production hostnames here
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Files
    |--------------------------------------------------------------------------
    |
    | The environment files that should be loaded, in order of precedence.
    | Later files override earlier files.
    |
    */

    'files' => [
        '.env',
        '.env.local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Variable Overrides
    |--------------------------------------------------------------------------
    |
    | Environment-specific variable overrides that are applied after loading
    | the environment files. These take precedence over file-based variables.
    |
    */

    'overrides' => [
        'local' => [
            'APP_DEBUG' => true,
            'LOG_LEVEL' => 'debug',
        ],

        'development' => [
            'APP_DEBUG' => true,
            'LOG_LEVEL' => 'debug',
        ],

        'staging' => [
            'APP_DEBUG' => false,
            'LOG_LEVEL' => 'info',
        ],

        'testing' => [
            'APP_DEBUG' => true,
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ],

        'production' => [
            'APP_DEBUG' => false,
            'LOG_LEVEL' => 'error',
        ],
    ],

];