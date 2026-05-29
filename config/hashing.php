<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver used to hash passwords for
    | your application. By default the bcrypt algorithm is used; you remain
    | free to change this to one of the argon variants if you prefer.
    |
    | Supported: "bcrypt", "argon", "argon2i", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'bcrypt'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Configuration options used when hashing with the Bcrypt algorithm. The
    | cost factor controls how long it takes to hash a password.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Configuration options used when hashing with the Argon algorithm. These
    | cost factors control the time and memory used to hash a password.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | When true the framework will automatically rehash a user's password on
    | login if the configured work factor has changed, allowing graceful
    | upgrades of stored hashes over time.
    |
    */

    'rehash_on_login' => true,

];
