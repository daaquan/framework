<?php

use Phare\Routing\DispatchForwardPayloadBuilder;

it('builds payload using url params when route has no typed params', function () {
    $builder = new DispatchForwardPayloadBuilder();
    $resolverCalled = false;

    $payload = $builder->build(
        [
            'namespace' => 'App\\Http\\Controllers',
            'controller' => 'User',
            'action' => 'show',
        ],
        ['id' => '42', 'slug' => 'alice'],
        function (array $types, array $params) use (&$resolverCalled) {
            $resolverCalled = true;

            return [];
        }
    );

    expect($payload)->toBe([
        'namespace' => 'App\\Http\\Controllers',
        'controller' => 'User',
        'action' => 'show',
        'params' => ['42', 'alice'],
    ]);
    expect($resolverCalled)->toBeFalse();
});

it('builds payload using typed param resolver when route has typed params', function () {
    $builder = new DispatchForwardPayloadBuilder();
    $seenTypes = [];
    $seenParams = [];

    $payload = $builder->build(
        [
            'namespace' => 'App\\Http\\Controllers',
            'controller' => 'User',
            'action' => 'update',
            'params' => ['int', \Phare\Http\Request::class],
        ],
        ['id' => '10'],
        function (array $types, array $params) use (&$seenTypes, &$seenParams) {
            $seenTypes = $types;
            $seenParams = $params;

            return [10, 'request-object'];
        }
    );

    expect($seenTypes)->toBe(['int', \Phare\Http\Request::class]);
    expect($seenParams)->toBe(['id' => '10']);
    expect($payload)->toBe([
        'namespace' => 'App\\Http\\Controllers',
        'controller' => 'User',
        'action' => 'update',
        'params' => [10, 'request-object'],
    ]);
});
