<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'App'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool)env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'ja_JP',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'phalcon' => [
        // https://docs.phalcon.io/5.0/en/db-models
        'orm' => [
            'cache_level' => 3, // 0: no cache, 1: cache metadata, 2: cache metadata and result sets, 3: cache metadata and result sets and build queries from cache
            'case_insensitive_column_map' => false, // Use lowercase keys when creating column maps
            'cast_last_insert_id_to_int' => false, // Cast the last inserted ID to int
            'cast_on_hydrate' => false, // Cast values during hydration
            'column_renaming' => true, // Enable column renaming
            'disable_assign_setters' => false, // Use setters when assigning properties
            'enable_implicit_joins' => true, // Enable implicit joins using model relations
            'enable_literals' => true, // Enable literal objects
            'events' => true, // Enable events
            'exception_on_failed_metadata_save' => true, // Throw exception when metadata save fails
            'exception_on_failed_save' => false, // Throw exception when model save fails
            'force_casting' => false, // Cast values retrieved from the database
            'ignore_unknown_columns' => false, // Ignore columns not defined in the model
            'late_state_binding' => false, // Late state binding of the Phalcon\Mvc\Model::cloneResultMap() method
            'not_null_validations' => true, // Allow NULL when the model property is NOT NULL
            'resultset_prefetch_records' => '0', // Number of records to prefetch
            'unique_cache_id' => 3, // Value to ensure unique cache IDs
            'update_snapshot_on_save' => true, // Update model snapshots on save
            'virtual_foreign_keys' => true, // Enable virtual foreign keys
        ],
        'db' => [
            'escape_identifiers' => 'On', // Escape identifiers in queries
            'force_casting' => 'Off', // Cast values retrieved from the database
        ],
        'warning.enable' => true, // Enable warnings
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [
        \Phare\Providers\LogServiceProvider::class,
        \Phare\Providers\ErrorHandlerProvider::class,
        \Phare\Providers\EncrypterProvider::class,
        \Phare\Providers\DispatcherProvider::class,
        \Phare\Providers\RouteServiceProvider::class,
        \Phare\Providers\RequestProvider::class,
        \Phare\Providers\ResponseProvider::class,
        \Phare\Providers\SessionProvider::class,
        \Phare\Providers\AuthServiceProvider::class,
        \Phare\Providers\ModelProvider::class,
        \Phare\Providers\DatabaseProvider::class,
        \Phare\Providers\ChronosProvider::class,
        \Phare\Providers\SqidsProvider::class,
    ],

    'aliases' => [
        'App' => \Phare\Support\Facades\Application::class,
        'DB' => \Phare\Support\Facades\DB::class,
        'Log' => \Phare\Support\Facades\Log::class,
        'Auth' => \Phare\Support\Facades\Auth::class,
        'Security' => \Phare\Support\Facades\Security::class,
        'Request' => \Phare\Support\Facades\Request::class,
        'Response' => \Phare\Support\Facades\Response::class,
    ],
];
