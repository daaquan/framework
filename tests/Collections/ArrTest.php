<?php

use Phare\Collections\Arr;

test('accessible checks array and ArrayAccess', function () {
    expect(Arr::accessible([]))->toBeTrue()
        ->and(Arr::accessible(new ArrayObject()))->toBeTrue()
        ->and(Arr::accessible('string'))->toBeFalse()
        ->and(Arr::accessible(123))->toBeFalse();
});

test('exists checks key existence', function () {
    expect(Arr::exists(['a' => 1], 'a'))->toBeTrue()
        ->and(Arr::exists(['a' => 1], 'b'))->toBeFalse();
});

test('dot flattens nested arrays', function () {
    $result = Arr::dot(['user' => ['name' => 'John', 'address' => ['city' => 'Tokyo']]]);
    expect($result)->toBe([
        'user.name' => 'John',
        'user.address.city' => 'Tokyo',
    ]);
});

test('undot expands dotted arrays', function () {
    $result = Arr::undot(['user.name' => 'John', 'user.address.city' => 'Tokyo']);
    expect($result)->toBe([
        'user' => ['name' => 'John', 'address' => ['city' => 'Tokyo']],
    ]);
});

test('except removes specified keys', function () {
    $result = Arr::except(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c']);
    expect($result)->toBe(['b' => 2]);
});

test('only returns specified keys', function () {
    $result = Arr::only(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c']);
    expect($result)->toBe(['a' => 1, 'c' => 3]);
});

test('forget removes keys from array', function () {
    $array = ['a' => 1, 'b' => 2, 'c' => 3];
    Arr::forget($array, ['a', 'c']);
    expect($array)->toBe(['b' => 2]);
});

test('forget handles dot notation', function () {
    $array = ['user' => ['name' => 'John', 'email' => 'john@test.com']];
    Arr::forget($array, 'user.email');
    expect($array)->toBe(['user' => ['name' => 'John']]);
});

test('hasDot checks nested keys', function () {
    $array = ['user' => ['name' => 'John']];
    expect(Arr::hasDot($array, 'user.name'))->toBeTrue()
        ->and(Arr::hasDot($array, 'user.email'))->toBeFalse();
});

test('getDot retrieves nested values', function () {
    $array = ['user' => ['name' => 'John', 'address' => ['city' => 'Tokyo']]];
    expect(Arr::getDot($array, 'user.name'))->toBe('John')
        ->and(Arr::getDot($array, 'user.address.city'))->toBe('Tokyo')
        ->and(Arr::getDot($array, 'missing', 'default'))->toBe('default');
});

test('setDot sets nested values', function () {
    $array = [];
    Arr::setDot($array, 'user.name', 'John');
    expect($array)->toBe(['user' => ['name' => 'John']]);
});

test('isAssoc identifies associative arrays', function () {
    expect(Arr::isAssoc(['a' => 1, 'b' => 2]))->toBeTrue()
        ->and(Arr::isAssoc([1, 2, 3]))->toBeFalse();
});

test('isList identifies sequential arrays', function () {
    expect(Arr::isList([1, 2, 3]))->toBeTrue()
        ->and(Arr::isList(['a' => 1]))->toBeFalse()
        ->and(Arr::isList([]))->toBeTrue();
});

test('join joins array elements', function () {
    expect(Arr::join(['a', 'b', 'c'], ', '))->toBe('a, b, c')
        ->and(Arr::join(['a', 'b', 'c'], ', ', ' and '))->toBe('a, b and c')
        ->and(Arr::join(['a'], ', ', ' and '))->toBe('a')
        ->and(Arr::join([], ', '))->toBe('');
});

test('prepend adds to beginning', function () {
    expect(Arr::prepend([2, 3], 1))->toBe([1, 2, 3])
        ->and(Arr::prepend(['b' => 2], 1, 'a'))->toBe(['a' => 1, 'b' => 2]);
});

test('pull gets and removes value', function () {
    $array = ['a' => 1, 'b' => 2];
    $value = Arr::pull($array, 'a');
    expect($value)->toBe(1)
        ->and($array)->toBe(['b' => 2]);
});

test('pull returns default for missing key', function () {
    $array = ['a' => 1];
    $value = Arr::pull($array, 'missing', 'default');
    expect($value)->toBe('default');
});

test('map applies callback to each element', function () {
    $result = Arr::map([1, 2, 3], fn ($v) => $v * 2);
    expect($result)->toBe([2, 4, 6]);
});

test('where filters array', function () {
    $result = Arr::where([1, 2, 3, 4, 5], fn ($v) => $v > 3);
    expect(array_values($result))->toBe([4, 5]);
});

test('whereNotNull filters nulls', function () {
    $result = Arr::whereNotNull([1, null, 2, null, 3]);
    expect(array_values($result))->toBe([1, 2, 3]);
});

test('wrap wraps values', function () {
    expect(Arr::wrap('hello'))->toBe(['hello'])
        ->and(Arr::wrap([1, 2]))->toBe([1, 2])
        ->and(Arr::wrap(null))->toBe([]);
});

test('toCssClasses builds class string', function () {
    $result = Arr::toCssClasses(['flex', 'active' => true, 'hidden' => false, 'text-bold']);
    expect($result)->toBe('flex active text-bold');
});

test('sortBy sorts by callback', function () {
    $array = ['b' => ['name' => 'Beta'], 'a' => ['name' => 'Alpha']];
    $result = Arr::sortBy($array, 'name');
    expect(array_keys($result))->toBe(['a', 'b']);
});

test('unique removes duplicates', function () {
    expect(array_values(Arr::unique([1, 2, 2, 3, 3])))->toBe([1, 2, 3]);
});

test('depth calculates nested depth', function () {
    expect(Arr::depth([1, 2, 3]))->toBe(1)
        ->and(Arr::depth([1, [2, [3]]]))->toBe(3);
});

test('containsDuplicateValue detects duplicates', function () {
    expect(Arr::containsDuplicateValue([1, 2, 2, 3]))->toBeTrue()
        ->and(Arr::containsDuplicateValue([1, 2, 3]))->toBeFalse();
});
