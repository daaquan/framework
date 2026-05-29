<?php

use Phalcon\Di\Di;
use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Foundation\Bootstrap\LoadConfiguration;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\Bootstrap\RegisterFacades;
use Phare\Foundation\Bootstrap\RegisterProviders;

function refreshHashHelperApp(): void
{
    Di::reset();

    $_ENV['APP_BASE_PATH'] = 'tests/Mock';
    $app = require $_ENV['APP_BASE_PATH'] . '/bootstrap/app.php';
    $app->bootstrapWith([
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterProviders::class,
        RegisterFacades::class,
    ]);

    Di::setDefault($app);
}

beforeEach(function () {
    refreshHashHelperApp();
});

test('bcrypt() produces a verifiable bcrypt hash', function () {
    $hash = bcrypt('secret');

    expect(password_get_info($hash)['algoName'])->toBe('bcrypt');
    expect(password_verify('secret', $hash))->toBeTrue();
});

test('bcrypt() output is a one-way hash, not reversible ciphertext', function () {
    $hash = bcrypt('secret');

    expect($hash)->toStartWith('$2');
    expect($hash)->not->toContain('secret');
});
