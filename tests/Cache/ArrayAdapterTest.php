<?php

use Phare\Cache\ArrayAdapter;

test('array adapter can set and get values', function () {
    $cache = new ArrayAdapter();
    $cache->set('key', 'value');

    expect($cache->get('key'))->toBe('value');
});

test('array adapter returns null for missing keys', function () {
    $cache = new ArrayAdapter();

    expect($cache->get('missing'))->toBeNull();
});

test('array adapter can delete values', function () {
    $cache = new ArrayAdapter();
    $cache->set('key', 'value');
    $result = $cache->delete('key');

    expect($result)->toBeTrue()
        ->and($cache->get('key'))->toBeNull();
});

test('array adapter can check key existence', function () {
    $cache = new ArrayAdapter();
    $cache->set('key', 'value');

    expect($cache->has('key'))->toBeTrue()
        ->and($cache->has('missing'))->toBeFalse();
});

test('array adapter can flush all values', function () {
    $cache = new ArrayAdapter();
    $cache->set('key1', 'value1');
    $cache->set('key2', 'value2');
    $result = $cache->clear();

    expect($result)->toBeTrue()
        ->and($cache->has('key1'))->toBeFalse()
        ->and($cache->has('key2'))->toBeFalse();
});

test('array adapter handles various value types', function () {
    $cache = new ArrayAdapter();

    $cache->set('string', 'hello');
    $cache->set('int', 42);
    $cache->set('float', 3.14);
    $cache->set('bool', true);
    $cache->set('array', ['a', 'b']);
    $cache->set('null', null);

    expect($cache->get('string'))->toBe('hello')
        ->and($cache->get('int'))->toBe(42)
        ->and($cache->get('float'))->toBe(3.14)
        ->and($cache->get('bool'))->toBeTrue()
        ->and($cache->get('array'))->toBe(['a', 'b'])
        ->and($cache->get('null'))->toBeNull();
});

test('array adapter respects TTL expiration', function () {
    $cache = new ArrayAdapter();
    $cache->set('key', 'value', -1);

    expect($cache->has('key'))->toBeFalse()
        ->and($cache->get('key'))->toBeNull();
});

test('array adapter non-expired keys are retrievable', function () {
    $cache = new ArrayAdapter();
    $cache->set('key', 'value', 3600);

    expect($cache->has('key'))->toBeTrue()
        ->and($cache->get('key'))->toBe('value');
});

test('array adapter getKeys returns all keys', function () {
    $cache = new ArrayAdapter();
    $cache->set('foo', 1);
    $cache->set('bar', 2);

    $keys = $cache->getKeys();
    expect($keys)->toContain('foo')
        ->and($keys)->toContain('bar');
});

test('array adapter getKeys with prefix', function () {
    $cache = new ArrayAdapter('test_');
    $cache->set('key1', 'value1');
    $cache->set('key2', 'value2');

    $keys = $cache->getKeys();
    expect($keys)->toContain('test_key1')
        ->and($keys)->toContain('test_key2');
});

test('array adapter prefix is prepended to keys', function () {
    $cache = new ArrayAdapter('cache_');
    $cache->set('key', 'value');

    expect($cache->get('key'))->toBe('value');
});

test('array adapter set returns true', function () {
    $cache = new ArrayAdapter();
    expect($cache->set('key', 'value'))->toBeTrue();
});

test('array adapter delete returns false for missing keys', function () {
    $cache = new ArrayAdapter();
    expect($cache->delete('nonexistent'))->toBeFalse();
});

test('array adapter increment', function () {
    $cache = new ArrayAdapter();
    $cache->set('counter', 5);

    expect($cache->increment('counter'))->toBe(6)
        ->and($cache->increment('counter', 3))->toBe(9);
});

test('array adapter decrement', function () {
    $cache = new ArrayAdapter();
    $cache->set('counter', 10);

    expect($cache->decrement('counter'))->toBe(9)
        ->and($cache->decrement('counter', 4))->toBe(5);
});

test('array adapter getMultiple', function () {
    $cache = new ArrayAdapter();
    $cache->set('a', 1);
    $cache->set('b', 2);

    $values = $cache->getMultiple(['a', 'b', 'c']);
    expect($values)->toBe(['a' => 1, 'b' => 2, 'c' => null]);
});

test('array adapter setMultiple', function () {
    $cache = new ArrayAdapter();
    $result = $cache->setMultiple(['x' => 10, 'y' => 20]);

    expect($result)->toBeTrue()
        ->and($cache->get('x'))->toBe(10)
        ->and($cache->get('y'))->toBe(20);
});

test('array adapter deleteMultiple', function () {
    $cache = new ArrayAdapter();
    $cache->set('a', 1);
    $cache->set('b', 2);
    $cache->set('c', 3);

    $result = $cache->deleteMultiple(['a', 'c']);
    expect($result)->toBeTrue()
        ->and($cache->has('a'))->toBeFalse()
        ->and($cache->has('b'))->toBeTrue()
        ->and($cache->has('c'))->toBeFalse();
});
