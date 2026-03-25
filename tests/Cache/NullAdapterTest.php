<?php

use Phare\Cache\NullAdapter;

test('null adapter never stores values', function () {
    $cache = new NullAdapter();

    expect($cache->set('key', 'value'))->toBeTrue()
        ->and($cache->get('key'))->toBeNull()
        ->and($cache->has('key'))->toBeFalse();
});

test('null adapter returns default value', function () {
    $cache = new NullAdapter();

    expect($cache->get('missing', 'default'))->toBe('default');
});

test('null adapter operations are no-op', function () {
    $cache = new NullAdapter();

    expect($cache->delete('k'))->toBeTrue()
        ->and($cache->clear())->toBeTrue()
        ->and($cache->getKeys())->toBe([])
        ->and($cache->increment('n'))->toBeFalse()
        ->and($cache->decrement('n'))->toBeFalse();
});
