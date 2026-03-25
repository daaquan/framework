<?php

use Phare\Collections\Collection;
use Phare\Config\Repository;

test('repository get supports scalar and many keys', function () {
    $repository = new Repository([
        'app' => ['name' => 'Phare', 'debug' => true],
        'queue' => ['default' => 'sync'],
    ]);

    expect($repository->get('app.name'))->toBe('Phare');
    expect($repository->get(['app.name', 'missing' => 'fallback']))->toBe([
        'app.name' => 'Phare',
        'missing' => 'fallback',
    ]);
});

test('repository push and prepend mutate target arrays', function () {
    $repository = new Repository(['app' => ['providers' => ['A']]]);

    $repository->push('app.providers', 'B');
    $repository->prepend('app.providers', 'Z');

    expect($repository->get('app.providers'))->toBe(['Z', 'A', 'B']);
});

test('repository typed accessors validate types', function () {
    $repository = new Repository([
        'app' => [
            'name' => 'Phare',
            'port' => 8080,
            'ratio' => 0.5,
            'debug' => true,
            'aliases' => ['App'],
        ],
    ]);

    expect($repository->string('app.name'))->toBe('Phare');
    expect($repository->integer('app.port'))->toBe(8080);
    expect($repository->float('app.ratio'))->toBe(0.5);
    expect($repository->boolean('app.debug'))->toBeTrue();
    expect($repository->array('app.aliases'))->toBe(['App']);
    expect($repository->collection('app.aliases'))->toBeInstanceOf(Collection::class);

    expect(fn () => $repository->integer('app.name'))->toThrow(InvalidArgumentException::class);
});

test('repository supports array access and merge', function () {
    $repository = new Repository(['app' => ['name' => 'Phare', 'debug' => false]]);

    expect(isset($repository['app.name']))->toBeTrue();
    expect($repository['app.name'])->toBe('Phare');

    $repository['app.debug'] = true;
    $repository->merge(['app' => ['env' => 'testing']]);

    expect($repository->path('app.debug'))->toBeTrue();
    expect($repository->path('app.env'))->toBe('testing');

    unset($repository['app.env']);

    expect($repository->has('app.env'))->toBeFalse();
});
