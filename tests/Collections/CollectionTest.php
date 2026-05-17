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
