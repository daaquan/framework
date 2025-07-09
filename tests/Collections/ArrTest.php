<?php

use Phare\Collections\Arr;

it('returns unique values from an array', function () {
    $array = [1, 2, 3, 2, 1];
    $unique = Arr::unique($array);
    expect($unique)->toEqual([1, 2, 3]);
});

it('returns keys of a given search value in an array', function () {
    $array = ['apple' => 'red', 'banana' => 'yellow', 'cherry' => 'red'];
    $keys = Arr::keys($array, 'red');
    expect($keys)->toEqual(['apple', 'cherry']);
});

it('excludes zero values from an array', function () {
    $array = [1, 0, 3, 0, 5];
    $result = Arr::excludeZero($array);
    expect($result)->toEqual([1, 3, 5]);
});

it('checks if an array contains any non-zero duplicate values', function () {
    $array = [1, 0, 3, 1, 5];
    $result = Arr::containsDuplicateValue($array);
    expect($result)->toBeTrue();

    $array = [1, 0, 3, 0, 5];
    $result = Arr::containsDuplicateValue($array);
    expect($result)->toBeFalse();
});

it('calculates the depth of a nested array', function () {
    $array = [1, 2, [3, [4, 5], 6], 7];
    $depth = Arr::depth($array);
    expect($depth)->toEqual(3);
});

it('fetches a value from an array by key or returns null if not found', function () {
    $array = ['apple' => 'red', 'banana' => 'yellow', 'cherry' => 'red'];
    $result = Arr::fetch($array, 'banana');
    expect($result)->toEqual('yellow');

    $result = Arr::fetch($array, 'orange');
    expect($result)->toBeNull();
});

it('returns the first element in an array', function () {
    $array = [1, 2, 3];
    $first = Arr::first($array);
    expect($first)->toEqual(1);
});

it('returns the last element in an array', function () {
    $array = [1, 2, 3];
    $last = Arr::last($array);
    expect($last)->toEqual(3);
});

it('sorts an array by key', function () {
    $array = [
        'a' => ['id' => 2, 'name' => 'A'],
        'b' => ['id' => 1, 'name' => 'B'],
        'c' => ['id' => 3, 'name' => 'C'],
    ];
    $sorted = Arr::order($array, 'id');
    expect($sorted)->toEqual([
        0 => ['id' => 1, 'name' => 'B'],
        1 => ['id' => 2, 'name' => 'A'],
        2 => ['id' => 3, 'name' => 'C'],
    ]);
});

it('can get value from an array', function () {
    $array = ['name' => 'John Doe', 'age' => 30];
    $value = Arr::get($array, 'name');
    expect($value)->toBe('John Doe');
});

it('can check if array has a key', function () {
    $array = ['name' => 'John Doe', 'age' => 30];
    $hasName = Arr::has($array, 'name');
    $hasAddress = Arr::has($array, 'address');
    expect($hasName)->toBeTrue();
    expect($hasAddress)->toBeFalse();
});

it('can fetch a flattened array', function () {
    $array = [
        'apple' => [
            'color' => 'red',
            'type' => 'fruit',
        ],
        'banana' => [
            'color' => 'yellow',
            'type' => 'fruit',
        ],
    ];
    $flattened = Arr::flatten($array);
    expect($flattened)->toBe(['red', 'fruit', 'yellow', 'fruit']);
});

it('can filter the array', function () {
    $array = [1, 2, 3, 4, 5];
    $filtered = Arr::filter($array, function ($item) {
        return $item > 3;
    });
    expect($filtered)->toBe([3 => 4, 4 => 5]);
});

it('can fetch a value from the array and remove it', function () {
    $array = ['name' => 'John', 'age' => 25, 'city' => 'New York'];
    $name = Arr::fetch($array, 'name');
    expect($name)->toBe('John');
});

it('can fetch the first key of the array', function () {
    $array = ['name' => 'John', 'age' => 25, 'city' => 'New York'];
    $firstKey = Arr::firstKey($array);
    expect($firstKey)->toBe('name');
});

it('can fetch the last key of the array', function () {
    $array = ['name' => 'John', 'age' => 25, 'city' => 'New York'];
    $lastKey = Arr::lastKey($array);
    expect($lastKey)->toBe('city');
});

// pluck
it('returns an array of values from an array of arrays, specified by key', function () {
    $array = [
        ['name' => 'apple', 'color' => 'red'],
        ['name' => 'banana', 'color' => 'yellow'],
        ['name' => 'cherry', 'color' => 'red'],
    ];
    $plucked = Arr::pluck($array, 'name');
    expect($plucked)->toEqual(['apple', 'banana', 'cherry']);
});

// chuck
it('chunks an array into chunks of the given size', function () {
    $array = [1, 2, 3, 4, 5];
    $chunked = Arr::chunk($array, 2);
    expect($chunked)->toEqual([[1, 2], [3, 4], [5]]);
});

// flatten
it('flattens a multi-dimensional array into a single level array', function () {
    $array = [1, 2, [3, [4, 5], 6], 7];
    $flattened = Arr::flatten($array);
    expect($flattened)->toEqual([1, 2, 3, [4, 5], 6, 7]);
    $flattenedDeep = Arr::flatten($array, true);
    expect($flattenedDeep)->toEqual([1, 2, 3, 4, 5, 6, 7]);
});

// group
it('groups an array by key', function () {
    $array = [
        ['name' => 'apple', 'color' => 'red'],
        ['name' => 'banana', 'color' => 'yellow'],
        ['name' => 'cherry', 'color' => 'red'],
    ];
    $grouped = Arr::group($array, 'color');
    expect($grouped)->toEqual([
        'red' => [
            ['name' => 'apple', 'color' => 'red'],
            ['name' => 'cherry', 'color' => 'red'],
        ],
        'yellow' => [
            ['name' => 'banana', 'color' => 'yellow'],
        ],
    ]);
});

// has
it('checks if an array has a given key', function () {
    $array = ['apple' => 'red', 'banana' => 'yellow', 'cherry' => 'red'];
    $result = Arr::has($array, 'banana');
    expect($result)->toBeTrue();

    $result = Arr::has($array, 'orange');
    expect($result)->toBeFalse();
});

// isUnique
it('checks if an array contains only unique values', function () {
    $array = [1, 2, 3];
    $result = Arr::isUnique($array);
    expect($result)->toBeTrue();

    $array = [1, 2, 3, 2];
    $result = Arr::isUnique($array);
    expect($result)->toBeFalse();
});

// lastKey
it('returns the last key in an array', function () {
    $array = ['apple' => 'red', 'banana' => 'yellow', 'cherry' => 'red'];
    $lastKey = Arr::lastKey($array);
    expect($lastKey)->toEqual('cherry');
});

// sliceLeft
it('returns the left part of an array', function () {
    $array = [1, 2, 3, 4, 5];
    $left = Arr::sliceLeft($array, 2);
    expect($left)->toEqual([1, 2]);
});

// sliceRight
it('returns the right part of an array', function () {
    $array = [1, 2, 3, 4, 5];
    $right = Arr::sliceRight($array, 2);
    expect($right)->toEqual([3, 4, 5]);
});

// split
it('splits an array into two parts', function () {
    $array = ['apple' => 'red', 'banana' => 'yellow', 'cherry' => 'red'];
    $split = Arr::split($array);
    expect($split)->toEqual([
        ['apple', 'banana', 'cherry'],
        ['red', 'yellow', 'red'],
    ]);
});

// whitelist
it('returns an array with only the keys specified in the whitelist', function () {
    $array = ['apple' => 'red', 'banana' => 'yellow', 'cherry' => 'red'];
    $whitelist = ['apple', 'cherry'];
    $result = Arr::whitelist($array, $whitelist);
    expect($result)->toEqual(['apple' => 'red', 'cherry' => 'red']);
});

// validateAll
it('validates all elements in an array', function () {
    $array = [1, 2, 3];
    $result = Arr::validateAll($array, fn ($value) => $value > 0);
    expect($result)->toBeTrue();

    $array = [1, 2, 3];
    $result = Arr::validateAll($array, fn ($value) => $value > 1);
    expect($result)->toBeFalse();
});

// validateAny
it('validates any element in an array', function () {
    $array = [1, 2, 3];
    $result = Arr::validateAny($array, fn ($value) => $value > 2);
    expect($result)->toBeTrue();

    $array = [1, 2, 3];
    $result = Arr::validateAny($array, fn ($value) => $value > 3);
    expect($result)->toBeFalse();
});
