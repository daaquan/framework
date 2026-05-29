<?php

use Phare\Config\Repository;
use Phare\Contracts\Config\Repository as RepositoryContract;

test('config Repository implements the config contract', function () {
    expect(new Repository())->toBeInstanceOf(RepositoryContract::class);
});

test('config Repository is macroable and macros can read config', function () {
    Repository::macro('firstSegment', function (string $key) {
        return explode('.', (string)$this->get($key))[0];
    });

    $repo = new Repository(['app' => ['name' => 'phare.web']]);

    expect($repo->firstSegment('app.name'))->toBe('phare');
    Repository::flushMacros();
});
