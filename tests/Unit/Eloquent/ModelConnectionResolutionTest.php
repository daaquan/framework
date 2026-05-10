<?php

use Phare\Database\MySql\DatabaseManager;
use Phare\Eloquent\Model;

function makeDbManagerStub(array $services, string $default = 'db'): DatabaseManager
{
    $stub = test()->createStub(DatabaseManager::class);
    $stub->method('hasConnectionService')
        ->willReturnCallback(fn (string $name) => $services[$name] ?? false);
    $stub->method('getDefaultConnection')->willReturn($default);

    return $stub;
}

it('keeps an explicitly-set connection without resolving', function () {
    $manager = makeDbManagerStub(['db' => true, 'reports' => true]);

    $resolved = Model::resolveConnectionName($manager, 'reports', \App\Models\Auth\User::class);

    expect($resolved)->toBe('reports');
});

it('uses the namespace-derived service name when registered', function () {
    $manager = makeDbManagerStub(['auth' => true, 'db' => true]);

    $resolved = Model::resolveConnectionName($manager, null, \App\Models\Auth\User::class);

    expect($resolved)->toBe('auth');
});

it('falls back to the db service when the namespace-derived name is not registered', function () {
    $manager = makeDbManagerStub(['db' => true]);

    $resolved = Model::resolveConnectionName($manager, null, \App\Models\Auth\User::class);

    expect($resolved)->toBe('db');
});

it('falls back to the default connection when neither namespaced nor db is registered', function () {
    $manager = makeDbManagerStub(['reports' => true], default: 'reports');

    $resolved = Model::resolveConnectionName($manager, null, \App\Models\Auth\User::class);

    expect($resolved)->toBe('reports');
});

it('falls back to the default connection for top-level classes with no namespace fragment', function () {
    $manager = makeDbManagerStub(['analytics' => true], default: 'analytics');

    $resolved = Model::resolveConnectionName($manager, null, 'TopLevelModel');

    expect($resolved)->toBe('analytics');
});
