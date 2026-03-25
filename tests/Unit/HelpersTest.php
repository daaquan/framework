<?php

it('normalizes URIs correctly', function () {
    expect(normalize_uri('foo', 'bar'))
        ->toBe('/foo/bar')
        ->and(normalize_uri('/foo/', '/bar/baz/'))
        ->toBe('/foo/bar/baz');
});

it('tap helper returns the value after executing callback', function () {
    $object = new stdClass();
    $result = tap($object, function ($obj) {
        $obj->called = true;
    });

    expect($result)->toBe($object)
        ->and($object->called)->toBeTrue();
});

it('evaluates blank and filled helper compatibility', function () {
    expect(blank(null))->toBeTrue()
        ->and(blank('   '))->toBeTrue()
        ->and(blank([]))->toBeTrue()
        ->and(blank(0))->toBeFalse()
        ->and(blank(false))->toBeFalse()
        ->and(filled('value'))->toBeTrue()
        ->and(filled(''))->toBeFalse();
});

it('rescue returns fallback and can skip reporting', function () {
    $result = rescue(
        fn () => throw new RuntimeException('boom'),
        fn (Throwable $e) => 'fallback:'.$e->getMessage(),
        false
    );

    expect($result)->toBe('fallback:boom');
});
