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
