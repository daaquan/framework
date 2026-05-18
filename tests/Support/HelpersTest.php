<?php

test('data_get reads a nested value via dot notation', function () {
    $data = ['user' => ['profile' => ['name' => 'Ann']]];

    expect(data_get($data, 'user.profile.name'))->toBe('Ann');
});

test('data_get returns the default when the path is missing', function () {
    expect(data_get(['a' => 1], 'a.b.c', 'fallback'))->toBe('fallback');
});

test('data_get reads from object properties', function () {
    $obj = (object)['meta' => (object)['id' => 7]];

    expect(data_get($obj, 'meta.id'))->toBe(7);
});

test('data_get supports the wildcard segment', function () {
    $data = ['rows' => [['v' => 1], ['v' => 2], ['v' => 3]]];

    expect(data_get($data, 'rows.*.v'))->toBe([1, 2, 3]);
});

test('data_set writes a nested value, creating intermediate arrays', function () {
    $data = [];
    data_set($data, 'a.b.c', 42);

    expect($data)->toBe(['a' => ['b' => ['c' => 42]]]);
});

test('data_set does not overwrite when overwrite is false', function () {
    $data = ['a' => ['b' => 1]];
    data_set($data, 'a.b', 99, false);

    expect($data['a']['b'])->toBe(1);
});

test('data_set with a wildcard writes to every member', function () {
    $data = ['rows' => [['v' => 0], ['v' => 0]]];
    data_set($data, 'rows.*.v', 5);

    expect($data['rows'])->toBe([['v' => 5], ['v' => 5]]);
});

test('data_fill only fills missing keys', function () {
    $data = ['a' => 1];
    data_fill($data, 'a', 99);
    data_fill($data, 'b', 2);

    expect($data)->toBe(['a' => 1, 'b' => 2]);
});

test('data_forget removes a nested value via dot notation', function () {
    $data = ['a' => ['b' => 1, 'c' => 2]];
    data_forget($data, 'a.b');

    expect($data)->toBe(['a' => ['c' => 2]]);
});

test('head returns the first element of an array', function () {
    expect(head([10, 20, 30]))->toBe(10);
});

test('last returns the last element of an array', function () {
    expect(last([10, 20, 30]))->toBe(30);
});

test('throw_if throws the given exception when the condition is truthy', function () {
    throw_if(true, RuntimeException::class, 'boom');
})->throws(RuntimeException::class, 'boom');

test('throw_if returns the condition when it is falsy', function () {
    expect(throw_if(false, RuntimeException::class))->toBeFalse();
});

test('throw_if accepts an exception instance', function () {
    throw_if(true, new LogicException('bad'));
})->throws(LogicException::class, 'bad');

test('throw_unless throws when the condition is falsy', function () {
    throw_unless(false, RuntimeException::class, 'missing');
})->throws(RuntimeException::class, 'missing');

test('throw_unless returns the condition when it is truthy', function () {
    expect(throw_unless('ok', RuntimeException::class))->toBe('ok');
});

test('e escapes HTML special characters', function () {
    expect(e('<a href="x">tom & jerry</a>'))
        ->toBe('&lt;a href=&quot;x&quot;&gt;tom &amp; jerry&lt;/a&gt;');
});

test('e returns an empty string for null', function () {
    expect(e(null))->toBe('');
});

test('transform applies the callback when the value is filled', function () {
    expect(transform(5, fn ($v) => $v * 2))->toBe(10);
});

test('transform returns the default when the value is blank', function () {
    expect(transform(null, fn ($v) => $v * 2, 'fallback'))->toBe('fallback');
});

test('transform resolves a callable default', function () {
    expect(transform('', fn ($v) => $v, fn () => 'computed'))->toBe('computed');
});

test('object_get reads a nested object property via dot notation', function () {
    $obj = (object)['profile' => (object)['name' => 'Ann']];

    expect(object_get($obj, 'profile.name'))->toBe('Ann');
});

test('object_get returns the default when a segment is missing', function () {
    $obj = (object)['profile' => (object)['name' => 'Ann']];

    expect(object_get($obj, 'profile.age', 0))->toBe(0);
});

test('preg_replace_array replaces matches sequentially from the array', function () {
    expect(preg_replace_array('/\?/', ['8:30', '9:00'], 'The event runs from ? to ?'))
        ->toBe('The event runs from 8:30 to 9:00');
});
