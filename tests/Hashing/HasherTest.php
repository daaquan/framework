<?php

use Phare\Hashing\ArgonHasher;
use Phare\Hashing\BcryptHasher;
use Phare\Hashing\HasherInterface;
use Phare\Hashing\HashManager;

test('bcrypt hasher defaults to 12 rounds', function () {
    $hasher = new BcryptHasher();
    $info = $hasher->info($hasher->make('secret'));

    expect($info['options']['cost'])->toBe(12);
});

test('bcrypt make/check round-trips', function () {
    $hasher = new BcryptHasher(['rounds' => 4]);
    $hash = $hasher->make('secret');

    expect($hasher->check('secret', $hash))->toBeTrue();
    expect($hasher->check('wrong', $hash))->toBeFalse();
});

test('value parameter is marked SensitiveParameter', function (string $class, string $method) {
    $param = (new ReflectionMethod($class, $method))->getParameters()[0];

    expect($param->getName())->toBe('value');
    expect($param->getAttributes(SensitiveParameter::class))->not->toBeEmpty();
})->with([
    [HasherInterface::class, 'make'],
    [HasherInterface::class, 'check'],
    [BcryptHasher::class, 'make'],
    [BcryptHasher::class, 'check'],
    [ArgonHasher::class, 'make'],
    [ArgonHasher::class, 'check'],
    [HashManager::class, 'make'],
    [HashManager::class, 'check'],
]);
