<?php

use Phare\Collections\Collection;

it('reject() keeps only items the callback returns false for', function () {
    $collection = new Collection([1, 2, 3, 4]);
    $result = $collection->reject(fn ($value) => $value % 2 === 0);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and(array_values($result->toArray()))->toBe([1, 3]);
});

it('reject() does not mutate the original collection', function () {
    $collection = new Collection([1, 2, 3]);
    $collection->reject(fn ($value) => $value > 1);

    expect($collection->toArray())->toBe([1, 2, 3]);
});

it('reduce() folds the collection into a single value', function () {
    $collection = new Collection([1, 2, 3, 4]);

    expect($collection->reduce(fn ($carry, $item) => $carry + $item, 0))->toBe(10);
});

it('reduce() uses the given initial value', function () {
    $collection = new Collection([1, 2]);

    expect($collection->reduce(fn ($carry, $item) => $carry + $item, 100))->toBe(103);
});

it('each() iterates over every item and returns the collection', function () {
    $collection = new Collection([1, 2, 3]);
    $seen = [];

    $result = $collection->each(function ($value) use (&$seen) {
        $seen[] = $value;
    });

    expect($seen)->toBe([1, 2, 3])
        ->and($result)->toBe($collection);
});

it('each() stops iterating when the callback returns false', function () {
    $collection = new Collection([1, 2, 3, 4]);
    $seen = [];

    $collection->each(function ($value) use (&$seen) {
        $seen[] = $value;

        return $value < 2 ? null : false;
    });

    expect($seen)->toBe([1, 2]);
});

it('every() returns true when all items satisfy the predicate', function () {
    $collection = new Collection([2, 4, 6]);

    expect($collection->every(fn ($value) => $value % 2 === 0))->toBeTrue();
});

it('every() returns false when any item fails the predicate', function () {
    $collection = new Collection([2, 3, 4]);

    expect($collection->every(fn ($value) => $value % 2 === 0))->toBeFalse();
});

it('reverse() reverses the items', function () {
    $collection = new Collection([1, 2, 3]);
    $result = $collection->reverse();

    expect($result)->toBeInstanceOf(Collection::class)
        ->and(array_values($result->toArray()))->toBe([3, 2, 1]);
});

it('reverse() does not mutate the original collection', function () {
    $collection = new Collection([1, 2, 3]);
    $collection->reverse();

    expect($collection->toArray())->toBe([1, 2, 3]);
});

it('concat() appends items from an array', function () {
    $collection = new Collection([1, 2]);
    $result = $collection->concat([3, 4]);

    expect(array_values($result->toArray()))->toBe([1, 2, 3, 4]);
});

it('concat() appends items from another collection', function () {
    $collection = new Collection([1, 2]);
    $result = $collection->concat(new Collection([3, 4]));

    expect(array_values($result->toArray()))->toBe([1, 2, 3, 4]);
});

it('collapse() flattens a collection of arrays one level deep', function () {
    $collection = new Collection([[1, 2], [3, 4], [5]]);
    $result = $collection->collapse();

    expect(array_values($result->toArray()))->toBe([1, 2, 3, 4, 5]);
});

it('nth() returns every nth element', function () {
    $collection = new Collection(['a', 'b', 'c', 'd', 'e', 'f']);

    expect(array_values($collection->nth(2)->toArray()))->toBe(['a', 'c', 'e']);
});

it('nth() honors the offset', function () {
    $collection = new Collection(['a', 'b', 'c', 'd', 'e', 'f']);

    expect(array_values($collection->nth(2, 1)->toArray()))->toBe(['b', 'd', 'f']);
});

it('tap() passes the collection to the callback and returns it', function () {
    $collection = new Collection([1, 2, 3]);
    $tapped = null;

    $result = $collection->tap(function ($collection) use (&$tapped) {
        $tapped = $collection;
    });

    expect($result)->toBe($collection)
        ->and($tapped)->toBe($collection);
});

it('pipe() returns the result of the callback', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->pipe(fn ($collection) => $collection->sum()))->toBe(6);
});

it('partition() splits the collection by a predicate', function () {
    $collection = new Collection([1, 2, 3, 4]);
    [$even, $odd] = $collection->partition(fn ($value) => $value % 2 === 0);

    expect(array_values($even->toArray()))->toBe([2, 4])
        ->and(array_values($odd->toArray()))->toBe([1, 3]);
});

it('partition() does not mutate the original collection', function () {
    $collection = new Collection([1, 2, 3]);
    $collection->partition(fn ($value) => $value > 1);

    expect($collection->toArray())->toBe([1, 2, 3]);
});

it('search() returns the key of a matching value', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->search('b'))->toBe(1);
});

it('search() returns false when the value is absent', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->search('z'))->toBeFalse();
});

it('search() accepts a predicate callback', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->search(fn ($value) => $value === 'c'))->toBe(2);
});

it('only() keeps just the given keys', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

    expect($collection->only('a', 'c')->toArray())->toBe(['a' => 1, 'c' => 3]);
});

it('only() accepts an array of keys', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

    expect($collection->only(['b'])->toArray())->toBe(['b' => 2]);
});

it('join() concatenates the items with a glue string', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->join(', '))->toBe('a, b, c');
});

it('join() uses a distinct final glue when given', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->join(', ', ' and '))->toBe('a, b and c');
});

it('join() returns a single item without any glue', function () {
    $collection = new Collection(['a']);

    expect($collection->join(', ', ' and '))->toBe('a');
});

it('join() returns an empty string for an empty collection', function () {
    $collection = new Collection([]);

    expect($collection->join(', '))->toBe('');
});

it('pad() pads the collection up to the given size', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->pad(5, 0)->toArray())->toBe([1, 2, 3, 0, 0]);
});

it('pad() pads to the left when the size is negative', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->pad(-5, 0)->toArray())->toBe([0, 0, 1, 2, 3]);
});

it('pad() is a no-op when the size is within the current count', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->pad(2, 0)->toArray())->toBe([1, 2, 3]);
});

it('where() filters array items by an attribute (equality)', function () {
    $users = new Collection([
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25],
        ['name' => 'Carol', 'age' => 30],
    ]);

    $result = $users->where('age', 30);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and(array_column($result->toArray(), 'name'))->toBe(['Alice', 'Carol']);
});

it('where() supports an explicit comparison operator', function () {
    $users = new Collection([
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25],
        ['name' => 'Carol', 'age' => 40],
    ]);

    $result = $users->where('age', '>', 26);

    expect(array_column($result->toArray(), 'name'))->toBe(['Alice', 'Carol']);
});

it('where() reads attributes from object items', function () {
    $items = new Collection([(object)['x' => 1], (object)['x' => 2], (object)['x' => 2]]);

    expect($items->where('x', 2)->count())->toBe(2);
});

it('where() does not mutate the original collection', function () {
    $users = new Collection([['age' => 1], ['age' => 2]]);
    $users->where('age', 1);

    expect($users->count())->toBe(2);
});

it('whereIn() keeps items whose attribute is in the set', function () {
    $users = new Collection([
        ['name' => 'Alice'],
        ['name' => 'Bob'],
        ['name' => 'Carol'],
    ]);

    $result = $users->whereIn('name', ['Bob', 'Carol']);

    expect(array_column($result->toArray(), 'name'))->toBe(['Bob', 'Carol']);
});

it('whereNotIn() removes items whose attribute is in the set', function () {
    $users = new Collection([
        ['name' => 'Alice'],
        ['name' => 'Bob'],
        ['name' => 'Carol'],
    ]);

    $result = $users->whereNotIn('name', ['Bob']);

    expect(array_column($result->toArray(), 'name'))->toBe(['Alice', 'Carol']);
});

it('firstWhere() returns the first matching item', function () {
    $users = new Collection([
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25],
    ]);

    expect($users->firstWhere('age', 25)['name'])->toBe('Bob');
});

it('firstWhere() returns null when nothing matches', function () {
    $users = new Collection([['name' => 'Alice']]);

    expect($users->firstWhere('name', 'Zed'))->toBeNull();
});

it('whenEmpty() runs the callback on an empty collection', function () {
    $collection = new Collection([]);
    $passed = null;

    $result = $collection->whenEmpty(function ($collection) use (&$passed) {
        $passed = $collection;
    });

    expect($passed)->toBe($collection)
        ->and($result)->toBe($collection);
});

it('whenEmpty() skips the callback on a non-empty collection', function () {
    $collection = new Collection([1]);
    $ran = false;

    $collection->whenEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeFalse();
});

it('whenEmpty() runs the default callback on a non-empty collection', function () {
    $collection = new Collection([1]);
    $which = null;

    $collection->whenEmpty(
        function () use (&$which) {
            $which = 'callback';
        },
        function () use (&$which) {
            $which = 'default';
        },
    );

    expect($which)->toBe('default');
});

it('whenNotEmpty() runs the callback on a non-empty collection', function () {
    $collection = new Collection([1]);
    $ran = false;

    $collection->whenNotEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('whenNotEmpty() skips the callback on an empty collection', function () {
    $collection = new Collection([]);
    $ran = false;

    $collection->whenNotEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeFalse();
});

it('unlessEmpty() runs the callback on a non-empty collection', function () {
    $collection = new Collection([1]);
    $ran = false;

    $collection->unlessEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('unlessNotEmpty() runs the callback on an empty collection', function () {
    $collection = new Collection([]);
    $ran = false;

    $collection->unlessNotEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('whereNull() keeps items whose attribute is null', function () {
    $users = new Collection([
        ['name' => 'Alice', 'deleted_at' => null],
        ['name' => 'Bob', 'deleted_at' => '2020'],
        ['name' => 'Carol', 'deleted_at' => null],
    ]);

    $result = $users->whereNull('deleted_at');

    expect(array_column($result->toArray(), 'name'))->toBe(['Alice', 'Carol']);
});

it('whereNotNull() keeps items whose attribute is not null', function () {
    $users = new Collection([
        ['name' => 'Alice', 'deleted_at' => null],
        ['name' => 'Bob', 'deleted_at' => '2020'],
    ]);

    expect(array_column($users->whereNotNull('deleted_at')->toArray(), 'name'))->toBe(['Bob']);
});

it('whereNull() without a key tests the items themselves', function () {
    $collection = new Collection([1, null, 2, null]);

    expect($collection->whereNull()->count())->toBe(2);
});

it('whereNotNull() without a key tests the items themselves', function () {
    $collection = new Collection([1, null, 2]);

    expect(array_values($collection->whereNotNull()->toArray()))->toBe([1, 2]);
});

it('before() returns the item preceding the given value', function () {
    $collection = new Collection(['a', 'b', 'c', 'd']);

    expect($collection->before('c'))->toBe('b');
});

it('before() returns null for the first item', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->before('a'))->toBeNull();
});

it('before() returns null when the value is absent', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->before('z'))->toBeNull();
});

it('after() returns the item following the given value', function () {
    $collection = new Collection(['a', 'b', 'c', 'd']);

    expect($collection->after('c'))->toBe('d');
});

it('after() returns null for the last item', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->after('c'))->toBeNull();
});

it('after() accepts a predicate callback', function () {
    $collection = new Collection(['a', 'b', 'c', 'd']);

    expect($collection->after(fn ($value) => $value === 'b'))->toBe('c');
});

it('make() creates a collection from an array', function () {
    expect(Collection::make([1, 2, 3])->toArray())->toBe([1, 2, 3]);
});

it('make() defaults to an empty collection', function () {
    expect(Collection::make()->toArray())->toBe([]);
});

it('make() unwraps an existing collection', function () {
    expect(Collection::make(new Collection([1, 2]))->toArray())->toBe([1, 2]);
});

it('times() builds a collection of sequential numbers', function () {
    expect(Collection::times(3)->toArray())->toBe([1, 2, 3]);
});

it('times() maps each number through the callback', function () {
    expect(Collection::times(3, fn ($n) => $n * 2)->toArray())->toBe([2, 4, 6]);
});

it('times() returns an empty collection for a non-positive count', function () {
    expect(Collection::times(0)->toArray())->toBe([]);
});

it('range() builds a collection over a numeric range', function () {
    expect(Collection::range(1, 5)->toArray())->toBe([1, 2, 3, 4, 5]);
});

it('range() honors the step', function () {
    expect(Collection::range(0, 10, 5)->toArray())->toBe([0, 5, 10]);
});

it('wrap() wraps a scalar into a collection', function () {
    expect(Collection::wrap('a')->toArray())->toBe(['a']);
});

it('wrap() leaves an array as-is', function () {
    expect(Collection::wrap([1, 2])->toArray())->toBe([1, 2]);
});

it('wrap() rewraps an existing collection', function () {
    expect(Collection::wrap(new Collection([1, 2]))->toArray())->toBe([1, 2]);
});

it('unwrap() extracts items from a collection', function () {
    expect(Collection::unwrap(new Collection([1, 2])))->toBe([1, 2]);
});

it('unwrap() returns a non-collection value unchanged', function () {
    expect(Collection::unwrap([1, 2]))->toBe([1, 2]);
});
