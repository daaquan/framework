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

test('add sets a key only when it is missing', function () {
    expect(Arr::add(['name' => 'Ann'], 'age', 30))->toBe(['name' => 'Ann', 'age' => 30]);
});

test('add leaves an existing key untouched', function () {
    expect(Arr::add(['name' => 'Ann'], 'name', 'Bob'))->toBe(['name' => 'Ann']);
});

test('add treats a present null value as missing', function () {
    expect(Arr::add(['name' => null], 'name', 'Ann'))->toBe(['name' => 'Ann']);
});

test('collapse flattens an array of arrays one level deep', function () {
    expect(Arr::collapse([[1, 2], [3, 4], [5]]))->toBe([1, 2, 3, 4, 5]);
});

test('collapse ignores non-array members', function () {
    expect(Arr::collapse([[1, 2], 'skip', [3]]))->toBe([1, 2, 3]);
});

test('crossJoin produces the cartesian product of the given arrays', function () {
    expect(Arr::crossJoin([1, 2], ['a', 'b']))
        ->toBe([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']]);
});

test('crossJoin of a single array wraps each element', function () {
    expect(Arr::crossJoin([1, 2]))->toBe([[1], [2]]);
});

test('divide splits an array into its keys and values', function () {
    expect(Arr::divide(['name' => 'Ann', 'age' => 30]))
        ->toBe([['name', 'age'], ['Ann', 30]]);
});

test('keyBy keys the array by a string attribute', function () {
    $rows = [['id' => 10, 'name' => 'Ann'], ['id' => 20, 'name' => 'Bob']];

    expect(Arr::keyBy($rows, 'id'))
        ->toBe([10 => ['id' => 10, 'name' => 'Ann'], 20 => ['id' => 20, 'name' => 'Bob']]);
});

test('keyBy keys the array by a callback', function () {
    expect(Arr::keyBy(['a', 'bb', 'ccc'], fn ($value) => strlen($value)))
        ->toBe([1 => 'a', 2 => 'bb', 3 => 'ccc']);
});

test('keyBy lets the last item win on a key collision', function () {
    $rows = [['t' => 'x', 'n' => 1], ['t' => 'x', 'n' => 2]];

    expect(Arr::keyBy($rows, 't'))->toBe(['x' => ['t' => 'x', 'n' => 2]]);
});

test('mapWithKeys remaps both keys and values', function () {
    $rows = [['id' => 1, 'name' => 'Ann'], ['id' => 2, 'name' => 'Bob']];

    expect(Arr::mapWithKeys($rows, fn ($row) => [$row['id'] => $row['name']]))
        ->toBe([1 => 'Ann', 2 => 'Bob']);
});

test('prependKeysWith prefixes every key', function () {
    expect(Arr::prependKeysWith(['name' => 'Ann', 'age' => 30], 'user.'))
        ->toBe(['user.name' => 'Ann', 'user.age' => 30]);
});

test('take returns the first N items for a positive limit', function () {
    expect(Arr::take([1, 2, 3, 4, 5], 3))->toBe([1, 2, 3]);
});

test('take returns the last N items for a negative limit', function () {
    expect(Arr::take([1, 2, 3, 4, 5], -2))->toBe([4, 5]);
});

test('hasAny is true when at least one key is present', function () {
    expect(Arr::hasAny(['name' => 'Ann', 'age' => 30], ['missing', 'age']))->toBeTrue();
});

test('hasAny is false when none of the keys are present', function () {
    expect(Arr::hasAny(['name' => 'Ann'], ['age', 'email']))->toBeFalse();
});

test('hasAny supports dot notation', function () {
    expect(Arr::hasAny(['user' => ['name' => 'Ann']], 'user.name'))->toBeTrue();
});

test('sortRecursive sorts list values and nested arrays', function () {
    expect(Arr::sortRecursive([3, 1, [9, 2], 2]))->toBe([1, 2, 3, [2, 9]]);
});

test('sortRecursive sorts associative arrays by key', function () {
    expect(Arr::sortRecursive(['c' => 3, 'a' => 1, 'b' => 2]))
        ->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
});

test('sortRecursiveDesc sorts list values in descending order', function () {
    expect(Arr::sortRecursiveDesc([1, 3, 2]))->toBe([3, 2, 1]);
});

test('random returns a single member of the array', function () {
    $array = [10, 20, 30];

    expect(in_array(Arr::random($array), $array, true))->toBeTrue();
});

test('random returns the requested number of distinct members', function () {
    $array = [1, 2, 3, 4, 5];
    $picked = Arr::random($array, 3);

    expect($picked)->toHaveCount(3)
        ->and(array_diff($picked, $array))->toBe([]);
});
