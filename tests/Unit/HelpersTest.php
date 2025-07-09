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
