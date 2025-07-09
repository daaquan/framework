<?php

use Phare\Collections\Collection;

// first and last
it('returns the first item of the collection', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $first = $collection->first();

    expect($first)->toBe(1);
});

it('returns the first item of the collection satisfying the given condition', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $first = $collection->first(function ($value) {
        return $value > 2;
    });

    expect($first)->toBe(3);
});

it('returns the last item of the collection', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $last = $collection->last();

    expect($last)->toBe(5);
});

it('returns the last item of the collection satisfying the given condition', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $last = $collection->last(function ($value) {
        return $value < 4;
    });

    expect($last)->toBe(3);
});

it('returns the default value when the collection is empty', function () {
    $collection = new Collection();

    $first = $collection->first(null, 'default');
    $last = $collection->last(null, 'default');

    expect($first)->toBe('default')
        ->and($last)->toBe('default');
});

it('returns the default value when no item satisfies the given condition', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $first = $collection->first(function ($value) {
        return $value > 10;
    }, 'default');

    expect($first)->toBe('default');

    $last = $collection->last(function ($value) {
        return $value < 0;
    }, 'default');

    expect($last)->toBe('default');
});

// group
it('groups items by the given key', function () {
    $collection = new Collection([
        ['category' => 'A', 'value' => 1],
        ['category' => 'B', 'value' => 2],
        ['category' => 'A', 'value' => 3],
        ['category' => 'B', 'value' => 4],
    ]);

    $grouped = $collection->group('category');

    expect($grouped['A'])->toEqual([
        ['category' => 'A', 'value' => 1],
        ['category' => 'A', 'value' => 3],
    ])->and($grouped['B'])->toEqual([
        ['category' => 'B', 'value' => 2],
        ['category' => 'B', 'value' => 4],
    ]);
});

it('groups items using a callback', function () {
    $collection = new Collection([
        ['category' => 'A', 'value' => 1],
        ['category' => 'B', 'value' => 2],
        ['category' => 'A', 'value' => 3],
        ['category' => 'B', 'value' => 4],
    ]);

    $grouped = $collection->group(function ($item) {
        return $item['category'];
    });

    expect($grouped['A'])->toEqual([
        ['category' => 'A', 'value' => 1],
        ['category' => 'A', 'value' => 3],
    ])->and($grouped['B'])->toEqual([
        ['category' => 'B', 'value' => 2],
        ['category' => 'B', 'value' => 4],
    ]);
});

// except
it('returns a new collection without the specified keys', function () {
    $collection = new Collection([
        'a' => 1,
        'b' => 2,
        'c' => 3,
        'd' => 4,
    ]);

    $except = $collection->except('b', 'd');

    expect($except->toArray())->toEqual([
        'a' => 1,
        'c' => 3,
    ]);
});

// mapWithKey and mapWithKeys
it('maps a callback with keys', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
    $result = $collection->mapWithKey(fn ($value, $key) => $value * 2);
    expect($result->toArray())->toEqual(['a' => 2, 'b' => 4, 'c' => 6]);
});

it('maps a callback with keys and merges results', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
    $result = $collection->mapWithKeys(fn ($value, $key) => [$key . '_double' => $value * 2]);
    expect($result->toArray())->toEqual(['a_double' => 2, 'b_double' => 4, 'c_double' => 6]);
});

// innerJoin
it('performs an inner join correctly', function () {
    $data = [
        [
            'id' => 1,
            'name' => 'John',
        ],
        [
            'id' => 2,
            'name' => 'Jane',
        ],
        [
            'id' => 3,
            'name' => 'Foo',
        ],
    ];
    $inner = [
        [
            'id' => 2,
            'email' => 'jane@example.com',
        ],
        [
            'id' => 4,
            'email' => 'piyo@example.com',
        ],
    ];
    $collection = new Collection($data);
    $joined = $collection->innerJoin(
        $inner,
        static fn ($data) => $data['id'],
        static fn ($inner) => $inner['id'],
        static fn ($data, $inner) => ['id' => $data['id'], 'name' => $data['name'], 'email' => $inner['email']]
    );

    expect($joined->toArray())->toBe([
        2 => [
            'id' => 2,
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ],
    ]);
});

// flatMap
it('flattens and maps the collection', function () {
    $input = [
        ['name' => 'Sally'],
        ['school' => 'Arkansas'],
        ['gender' => 'Female'],
        ['gender' => 'Male'],
    ];

    $expected = [
        'name' => 'SALLY',
        'school' => 'ARKANSAS',
        'gender' => 'MALE',
    ];

    $collection = new Collection($input);
    $result = $collection->flatMap('strtoupper');

    expect($result->toArray())->toBe($expected);
});

it('flattens and maps the collection with a callback', function () {
    $input = [
        ['name' => 'sallY'],
        ['school' => 'arkaNsaS'],
        ['gender' => 'feMale'],
        ['gender' => 'mALe'],
    ];

    $expected = [
        'name' => 'Sally',
        'school' => 'Arkansas',
        'gender' => 'Male',
    ];

    $collection = new Collection($input);
    $result = $collection->flatMap(function ($value) {
        return strtoupper(substr($value, 0, 1)) . strtolower(substr($value, 1));
    });

    expect($result->toArray())->toBe($expected);
});

// filter and values
it('filters the collection items using a callback or removes null values', function () {
    $collection = new Collection([1, null, 3, null, 5]);
    $result = $collection->filter();
    expect($result->values()->toArray())->toEqual([1, 3, 5]);

    $collection = new Collection([1, 2, 3, 4, 5]);
    $result = $collection->filter(fn ($value) => $value % 2 === 0);
    expect($result->values()->toArray())->toEqual([2, 4]);
});

it('filters the collection without a callback', function () {
    $input = [1, 2, 3, null, 5, false, 0, '', 8, 9];
    $expected = [1, 2, 3, 5, 8, 9];

    $collection = new Collection($input);
    $result = $collection->filter();

    expect($result->values()->toArray())->toBe($expected);
});

it('filters the collection with a callback', function () {
    $input = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    $expected = [2, 4, 6, 8];

    $collection = new Collection($input);
    $result = $collection->filter(function ($value) {
        return $value % 2 === 0;
    });

    expect($result->values()->toArray())->toBe($expected);
});

it('filters the collection with a callback and keys', function () {
    $input = [
        'apple' => 5,
        'banana' => 3,
        'orange' => 7,
        'grape' => 2,
    ];

    $expected = ['apple' => 5];

    $collection = new Collection($input);
    $result = $collection->filterWithKeys(function ($value, $key) {
        return strlen($key) <= 5 && $value > 3;
    });

    expect($result->toArray())->toBe($expected);
});

// flip
it('flips the collection', function () {
    $input = [
        'apple' => 'red',
        'banana' => 'yellow',
        'orange' => 'orange',
    ];

    $expected = [
        'red' => 'apple',
        'yellow' => 'banana',
        'orange' => 'orange',
    ];

    $collection = new Collection($input);
    $result = $collection->flip();

    expect($result->toArray())->toBe($expected);
});

it('checks if value exists in collection (strict)', function () {
    $input = [1, 2, 3, 4, '5', 6];

    $collection = new Collection($input);

    expect($collection->exists(5))->toBeFalse()
        ->and($collection->exists('5'))->toBeTrue();
});

it('checks if value exists in collection (non-strict)', function () {
    $input = [1, 2, 3, 4, '5', 6];

    $collection = new Collection($input);

    expect($collection->exists(5, false))->toBeTrue()
        ->and($collection->exists('5', false))->toBeTrue();
});

it('implodes the collection', function () {
    $input = ['apple', 'banana', 'orange'];
    $expected = 'apple,banana,orange';

    $collection = new Collection($input);
    $result = $collection->implode(',');

    expect($result)->toBe($expected);
});

it('merges two collections', function () {
    $input1 = [1, 2, 3];
    $input2 = [4, 5, 6];
    $expected = [1, 2, 3, 4, 5, 6];

    $collection1 = new Collection($input1);
    $result = $collection1->merge($input2);

    expect($result->toArray())->toBe($expected);
});

it('zips two collections', function () {
    $input1 = [1, 2, 3];
    $input2 = ['a', 'b', 'c'];
    $expected = [[1, 'a'], [2, 'b'], [3, 'c']];

    $collection1 = new Collection($input1);
    $result = $collection1->zip($input2);

    expect($result->toArray())->toBe($expected);
});

it('removes duplicates from the collection', function () {
    $input = [1, 2, 3, 2, 1, 4, 5, 6, 4];
    $expected = [1, 2, 3, 4, 5, 6];

    $collection = new Collection($input);
    $result = $collection->unique();

    expect($result->values()->toArray())->toBe($expected);
});

it('flattens the collection', function () {
    $input = [1, [2, [3, [4, 5], 6]], 7];
    $expected = [1, 2, 3, 4, 5, 6, 7];

    $collection = new Collection($input);
    $result = $collection->flatten();

    expect($result->toArray())->toBe($expected);
});

it('flattens the collection to a specific depth', function () {
    $input = [1, [2, [3, [4, 5], 6]], 7];
    $expected = [1, 2, [3, [4, 5], 6], 7];

    $collection = new Collection($input);
    $result = $collection->flatten(1);

    expect($result->toArray())->toBe($expected);
});

it('takes elements from the beginning of the collection', function () {
    $input = [1, 2, 3, 4, 5];
    $expected = [1, 2, 3];

    $collection = new Collection($input);
    $result = $collection->take(3);

    expect($result->toArray())->toBe($expected);
});

it('takes elements from the collection while the condition is true', function () {
    $input = [1, 2, 3, 4, 5];
    $expected = [1, 2];

    $collection = new Collection($input);
    $result = $collection->takeWhile(fn ($value) => $value < 3);

    expect($result->toArray())->toBe($expected);
});

it('skips elements from the beginning of the collection', function () {
    $input = [1, 2, 3, 4, 5];
    $expected = [3 => 4, 4 => 5];

    $collection = new Collection($input);
    $result = $collection->skip(3);

    expect($result->toArray())->toBe($expected);
});

it('skips elements from the collection while the condition is true', function () {
    $input = [1, 2, 3, 4, 5];
    $expected = [2 => 3, 3 => 4, 4 => 5];

    $collection = new Collection($input);
    $result = $collection->skipWhile(fn ($value) => $value < 3);

    expect($result->toArray())->toBe($expected);
});

it('slices the collection from a given offset and length', function () {
    $input = [1, 2, 3, 4, 5];
    $expected = [2 => 3, 3 => 4];

    $collection = new Collection($input);
    $result = $collection->slice(2, 2);

    expect($result->toArray())->toBe($expected);
});

it('plucks elements by attribute from the collection', function () {
    $input = [
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25],
        ['name' => 'Charlie', 'age' => 35],
    ];
    $expected = ['Alice', 'Bob', 'Charlie'];

    $collection = new Collection($input);
    $result = $collection->pluck('name');

    expect($result->toArray())->toBe($expected);
});

it('chunks the collection into chunks of given size with preserving keys', function () {
    $input = [1, 2, 3, 4, 5];
    $expected = [[0 => 1, 1 => 2], [2 => 3, 3 => 4], [4 => 5]];

    $collection = new Collection($input);
    $result = $collection->chunk(2, true);

    expect($result->toArray())->toBe($expected);
});

it('chunks the collection into chunks of given size without preserving keys', function () {
    $input = [1, 2, 3, 4, 5];
    $expected = [[1, 2], [3, 4], [5]];

    $collection = new Collection($input);
    $result = $collection->chunk(2, false);

    expect($result->toArray())->toBe($expected);
});

it('checks if the collection contains a specific value', function () {
    $input = [1, 2, 3, 4, 5];

    $collection = new Collection($input);

    expect($collection->contains(3))->toBeTrue()
        ->and($collection->contains(6))->toBeFalse();
});

it('checks if the collection contains a specific key', function () {
    $input = [
        'name' => 'Alice',
        'age' => 30,
        'city' => 'New York',
    ];

    $collection = new Collection($input);

    expect($collection->containsKey('age'))->toBeTrue()
        ->and($collection->containsKey('country'))->toBeFalse();
});

// sort
it('sorts the collection by a specific attribute', function () {
    $input = ['b', 'a', 'c'];
    $expected = ['a', 'b', 'c'];

    $collection = new Collection($input);
    $result = $collection->sort();

    expect($result->toArray())->toBe($expected);
});

it('sorts the collection using a callback', function () {
    $input = [3, 1, 2];
    $expected = [1, 2, 3];

    $collection = new Collection($input);
    $result = $collection->sortBy(function ($item) {
        return $item;
    });

    expect($result->toArray())->toBe($expected);
});

it('reverse sorts the collection by a specific attribute', function () {
    $input = ['b', 'a', 'c'];
    $expected = ['c', 'b', 'a'];

    $collection = new Collection($input);
    $result = $collection->rsort();

    expect($result->toArray())->toBe($expected);
});

it('reverse sorts the collection using a callback', function () {
    $input = [3, 1, 2];
    $expected = [3, 2, 1];

    $collection = new Collection($input);
    $result = $collection->rsortBy(function ($item) {
        return $item;
    });

    expect($result->toArray())->toBe($expected);
});

it('sorts the collection by key', function () {
    $input = ['b' => 2, 'a' => 1, 'c' => 3];
    $expected = ['a' => 1, 'b' => 2, 'c' => 3];

    $collection = new Collection($input);
    $result = $collection->sortKey();

    expect($result->toArray())->toBe($expected);
});

it('reverse sorts the collection by key', function () {
    $input = ['b' => 2, 'a' => 1, 'c' => 3];
    $expected = ['c' => 3, 'b' => 2, 'a' => 1];

    $collection = new Collection($input);
    $result = $collection->rsortKey();

    expect($result->toArray())->toBe($expected);
});

// math
it('returns the maximum value from the collection', function () {
    $input = [3, 1, 5, 2];
    $expected = 5;

    $collection = new Collection($input);
    $result = $collection->max();

    expect($result)->toBe($expected);
});

it('returns the minimum value from the collection', function () {
    $input = [3, 1, 5, 2];
    $expected = 1;

    $collection = new Collection($input);
    $result = $collection->min();

    expect($result)->toBe($expected);
});

it('returns the sum of the collection values', function () {
    $input = [3, 1, 5, 2];
    $expected = 11;

    $collection = new Collection($input);
    $result = $collection->sum();

    expect($result)->toBe($expected);
});

it('returns the average of the collection values', function () {
    $input = [3, 1, 5, 2];
    $expected = 2.75;

    $collection = new Collection($input);
    $result = $collection->avg();

    expect($result)->toBe($expected);
});

it('returns the median of the collection values', function () {
    $input = [3, 1, 5, 2];
    $expected = 2.5;

    $collection = new Collection($input);
    $result = $collection->median();

    expect($result)->toBe($expected);
});

// random and shuffle
it('returns random elements from the collection', function () {
    $input = [3, 1, 5, 2];
    $count = 2;

    $collection = new Collection($input);
    $result = $collection->random($count);

    expect($result)->toHaveCount($count);
    foreach ($result as $item) {
        expect($input)->toContain($item);
    }
});

it('shuffles the collection', function () {
    $input = [3, 1, 5, 2, 4, 6, 7, 8, 9, 10];

    $collection = new Collection($input);
    $shuffled = $collection->shuffle();

    expect($shuffled)->toBeInstanceOf(Collection::class)
        ->and($shuffled->implode(','))
        ->not()->toBe(implode(',', $input));
});

it('removes and returns the last element from the collection', function () {
    $input = [3, 1, 5, 2];
    $expected = 2;

    $collection = new Collection($input);
    $result = $collection->pop();

    expect($result)->toBe($expected);
});

it('removes and returns the first element from the collection', function () {
    $input = [3, 1, 5, 2];
    $expected = 3;

    $collection = new Collection($input);
    $result = $collection->shift();

    expect($result)->toBe($expected);
});

it('adds values to the beginning of the collection', function () {
    $input = [3, 1, 5, 2];
    $values = [7, 8];

    $collection = new Collection($input);
    $collection->prepend(...$values);

    expect($collection->toArray())->toBe([8, 7, 3, 1, 5, 2]);
});

it('adds values to the end of the collection', function () {
    $input = [3, 1, 5, 2];
    $values = [7, 8];

    $collection = new Collection($input);
    $collection->push(...$values);

    expect($collection->toArray())->toBe([3, 1, 5, 2, 7, 8]);
});

it('removes and returns a value from the collection by key', function () {
    $input = ['a' => 3, 'b' => 1, 'c' => 5, 'd' => 2];
    $key = 'b';
    $expected = 1;

    $collection = new Collection($input);
    $result = $collection->pull($key);

    expect($result)->toBe($expected);
});

it('calls the given callback when the condition is true', function () {
    $input = [3, 1, 5, 2];
    $condition = true;

    $collection = new Collection($input);
    $collection->when($condition, function ($c) {
        $c->push(10);
    });

    expect($collection->toArray())->toBe(array_merge($input, [10]));
});

it('does not call the given callback when the condition is false', function () {
    $input = [3, 1, 5, 2];
    $condition = false;

    $collection = new Collection($input);
    $collection->when($condition, function ($c) {
        $c->push(10);
    });

    expect($collection->toArray())->toBe($input);
});

it('calls the given callback when the condition is false in unless', function () {
    $input = [3, 1, 5, 2];
    $condition = false;

    $collection = new Collection($input);
    $collection->unless($condition, function ($c) {
        $c->push(10);
    });

    expect($collection->toArray())->toBe(array_merge($input, [10]));
});

it('does not call the given callback when the condition is true in unless', function () {
    $input = [3, 1, 5, 2];
    $condition = true;
    $collection = new Collection($input);
    $collection->unless($condition, function ($c) {
        $c->push(10);
    });

    expect($collection->toArray())->toBe($input);
});

it('returns the difference between the collection and the given array in diff', function () {
    $input = [1, 2, 3, 4, 5];
    $diff = [2, 4];

    $collection = new Collection($input);
    $result = $collection->diff($diff);

    expect($result->toArray())->toBe([0 => 1, 2 => 3, 4 => 5]);
});

it('returns duplicates in the collection with duplicates method', function () {
    $input = [1, 2, 2, 3, 4, 4, 5];
    $collection = new Collection($input);
    $result = $collection->duplicates();

    expect($result->toArray())->toBe([2 => 2, 5 => 4]);
});

it('returns true if the collection is empty in isEmpty', function () {
    $collection = new Collection([]);
    $result = $collection->isEmpty();

    expect($result)->toBeTrue();
});

it('returns false if the collection is not empty in isEmpty', function () {
    $collection = new Collection([1, 2, 3]);
    $result = $collection->isEmpty();

    expect($result)->toBeFalse();
});

it('returns true if the collection is not empty in isNotEmpty', function () {
    $collection = new Collection([1, 2, 3]);
    $result = $collection->isNotEmpty();

    expect($result)->toBeTrue();
});

it('returns false if the collection is empty in isNotEmpty', function () {
    $collection = new Collection([]);
    $result = $collection->isNotEmpty();

    expect($result)->toBeFalse();
});

it('returns count by specified attribute in countBy', function () {
    $input = ['apple', 'banana', 'apple', 'orange', 'banana', 'apple'];
    $collection = new Collection($input);
    $result = $collection->countBy();

    expect($result->toArray())->toBe([
        'apple' => 3,
        'banana' => 2,
        'orange' => 1,
    ]);
});

it('returns collection without elements from inner array using exceptBy', function () {
    $outer = [
        [
            'id' => 1,
            'name' => 'John',
        ],
        [
            'id' => 2,
            'name' => 'Jane',
        ],
        [
            'id' => 3,
            'name' => 'Hoge',
        ],
    ];

    $inner = [
        [
            'id' => 1,
            'email' => 'john@example.com',
        ],
        [
            'id' => 2,
            'email' => 'jane@example.com',
        ],
    ];

    $collection = new Collection($outer);

    $excepted = $collection->exceptBy(
        $inner,
        static fn ($outer) => $outer['id'],
        static fn ($inner) => $inner['id'],
    );

    expect($excepted->toArray())->toBe([
        [
            'id' => 3,
            'name' => 'Hoge',
        ],
    ]);
});

it('returns the maximum element by the provided callback in maxBy', function () {
    $input = [
        ['id' => 1, 'value' => 3],
        ['id' => 2, 'value' => 5],
        ['id' => 3, 'value' => 1],
    ];
    $collection = new Collection($input);
    $result = $collection->maxBy(function ($item) {
        return $item['value'];
    });

    expect($result)->toBe(['id' => 2, 'value' => 5]);
});

it('returns the minimum element by the provided callback in minBy', function () {
    $input = [
        ['id' => 1, 'value' => 3],
        ['id' => 2, 'value' => 5],
        ['id' => 3, 'value' => 1],
    ];
    $collection = new Collection($input);
    $result = $collection->minBy(function ($item) {
        return $item['value'];
    });

    expect($result)->toBe(['id' => 3, 'value' => 1]);
});

it('returns the intersection of the collection and the given array in intersect', function () {
    $collection = new Collection([1, 2, 3]);
    $inner = [2, 3, 4, 5];
    $result = $collection->intersect($inner);

    expect($result->toArray())->toBe([1 => 2, 2 => 3]);
});
