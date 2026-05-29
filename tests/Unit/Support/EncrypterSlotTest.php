<?php

use Phalcon\Di\Di;
use Phare\Encryption\Encrypter;
use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Foundation\Bootstrap\LoadConfiguration;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\Bootstrap\RegisterFacades;
use Phare\Foundation\Bootstrap\RegisterProviders;

function refreshEncrypterSlotApp(): void
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
    refreshEncrypterSlotApp();

    // Valid 32-byte key for aes-256-cbc; uppercase cipher exercises the
    // case-normalisation that the Phare Encrypter requires.
    config([
        'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
        'app.cipher' => 'AES-256-CBC',
    ]);
});

test("the 'encrypter' slot resolves to the Phare Encrypter, not Phalcon Crypt", function () {
    expect(app('encrypter'))->toBeInstanceOf(Encrypter::class);
});

test('encrypt()/decrypt() helpers round-trip values through the Phare Encrypter', function () {
    $payload = ['name' => 'John', 'age' => 30];

    $encrypted = encrypt($payload);

    expect($encrypted)->toBeString();
    expect(decrypt($encrypted))->toBe($payload);
});

test('encrypt() output does not leak the plaintext', function () {
    expect(encrypt('top-secret'))->not->toContain('top-secret');
});
