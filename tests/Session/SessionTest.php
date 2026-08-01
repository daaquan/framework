<?php

use Phalcon\Session\Adapter\Redis;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Storage\AdapterFactory;
use Phalcon\Storage\SerializerFactory;
use Phare\Session\SessionManager;

function redisSessionAvailable(): bool
{
    $socket = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 0.2);
    if ($socket === false) {
        return false;
    }

    fclose($socket);

    return true;
}

$sessionDatasets = [
    'file' => function () {
        $adapter = new Stream(['savePath' => sys_get_temp_dir()]);
        $session = (new SessionManager())->setAdapter($adapter);
        $session->start();

        return $session;
    },
];

if (redisSessionAvailable()) {
    $sessionDatasets['redis'] = function () {
        $factory = new AdapterFactory(new SerializerFactory());
        $adapter = new Redis(
            $factory,
            [
                'host' => '127.0.0.1',
                'port' => 6379,
            ]
        );
        $session = (new SessionManager())->setAdapter($adapter);
        $session->start();

        return $session;
    };
}

dataset('session', $sessionDatasets);

it('can set and get session values', function ($session) {
    $session->put('key', 'value');
    expect($session->get('key'))->toBe('value');
})->with('session');

it('can remove session values', function ($session) {
    $session->put('key', 'value');
    $session->forget('key');
    expect($session->get('key'))->toBeNull();
})->with('session');

it('can check if session has a value', function ($session) {
    $session->put('key', 'value');
    expect($session->has('key'))->toBeTrue();
    $session->forget('key');
    expect($session->has('key'))->toBeFalse();
})->with('session');

it('can pull session values', function ($session) {
    $session->put('key', 'value');
    $pulledValue = $session->pull('key');
    expect($pulledValue)->toBe('value')
        ->and($session->get('key'))->toBeNull();
})->with('session');

it('can add an array of values to session', function ($session) {
    $session->forget('key');

    $session->add('key', 'value1');
    $session->add('key', 'value2');
    expect($session->get('key'))
        ->toBeArray()
        ->and($session->get('key'))
        ->toHaveCount(2)
        ->and($session->get('key'))
        ->toEqual([
            'value1',
            'value2',
        ]);
})->with('session');

it('can replace session values', function ($session) {
    $session->put('key1', 'value1');
    $session->put('key2', 'value2');
    $session->replace(['key1' => 'new_value1', 'key2' => 'new_value2']);
    expect($session->get('key1'))->toBe('new_value1')
        ->and($session->get('key2'))->toBe('new_value2');
})->with('session');

it('can clear the session', function ($session) {
    $session->put('key1', 'value1');
    $session->put('key2', 'value2');
    $session->clear();
    expect($session->get('key1'))->toBe(null);
    expect($session->get('key2'))->toBe(null);
})->with('session');

it('can retrieve and remove an item from the session', function ($session) {
    $session->put('key', 'value');
    expect($session->pull('key'))->toBe('value');
    expect($session->get('key'))->toBe(null);
})->with('session');
