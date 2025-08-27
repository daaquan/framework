<?php

use Phare\Collections\Collection;

beforeEach(function () {
    $this->collection = new Collection([1, 2, 3, 4, 5]);
    $this->stringCollection = new Collection(['apple', 'banana', 'cherry']);
    $this->userCollection = new Collection([
        ['id' => 1, 'name' => 'John', 'age' => 30, 'active' => true],
        ['id' => 2, 'name' => 'Jane', 'age' => 25, 'active' => false],
        ['id' => 3, 'name' => 'Bob', 'age' => 35, 'active' => true],
    ]);
});

it('filters items with callback', function () {
    $filtered = $this->collection->filter(fn($item) => $item > 3);
    expect($filtered->toArray())->toBe([3 => 4, 4 => 5]);
});

it('maps over items', function () {
    $mapped = $this->collection->map(fn($item) => $item * 2);
    expect($mapped->toArray())->toBe([2, 4, 6, 8, 10]);
});

it('maps with keys', function () {
    $mapped = $this->collection->mapWithKey(fn($value, $key) => $value + $key);
    expect($mapped->toArray())->toBe([1, 3, 5, 7, 9]);
});

it('gets first item with callback', function () {
    $first = $this->collection->first(fn($item) => $item > 3);
    expect($first)->toBe(4);

    $firstDefault = $this->collection->first(fn($item) => $item > 10, 'default');
    expect($firstDefault)->toBe('default');
});

it('gets last item with callback', function () {
    $last = $this->collection->last(fn($item) => $item < 4);
    expect($last)->toBe(3);
});

it('takes specified number of items', function () {
    $taken = $this->collection->take(3);
    expect($taken->toArray())->toBe([1, 2, 3]);

    $takenNegative = $this->collection->take(-2);
    expect($takenNegative->toArray())->toBe([3 => 4, 4 => 5]);
});

it('skips specified number of items', function () {
    $skipped = $this->collection->skip(2);
    expect($skipped->toArray())->toBe([2 => 3, 3 => 4, 4 => 5]);
});

it('slices collection', function () {
    $sliced = $this->collection->slice(1, 3);
    expect($sliced->toArray())->toBe([1 => 2, 2 => 3, 3 => 4]);
});

it('chunks collection into smaller arrays', function () {
    $chunked = $this->collection->chunk(2);
    expect($chunked->toArray())->toHaveCount(3);
    expect($chunked->toArray()[0])->toBe([0 => 1, 1 => 2]);
});

it('checks if collection contains value', function () {
    expect($this->collection->contains(3))->toBe(true);
    expect($this->collection->contains(10))->toBe(false);
});

it('checks if collection is empty', function () {
    expect($this->collection->isEmpty())->toBe(false);
    expect($this->collection->isNotEmpty())->toBe(true);

    $empty = new Collection([]);
    expect($empty->isEmpty())->toBe(true);
    expect($empty->isNotEmpty())->toBe(false);
});

it('sorts collection', function () {
    $unsorted = new Collection([3, 1, 4, 1, 5]);
    $sorted = $unsorted->sort();
    expect($sorted->toArray())->toBe([1, 1, 3, 4, 5]);
});

it('sorts collection by callback', function () {
    $sortedByAge = $this->userCollection->sortBy(fn($user) => $user['age']);
    expect($sortedByAge->toArray()[0]['name'])->toBe('Jane');
});

it('calculates sum', function () {
    expect($this->collection->sum())->toBe(15);
});

it('calculates average', function () {
    expect($this->collection->avg())->toBe(3);
});

it('finds max value', function () {
    expect($this->collection->max())->toBe(5);
});

it('finds min value', function () {
    expect($this->collection->min())->toBe(1);
});

it('gets unique values', function () {
    $duplicated = new Collection([1, 2, 2, 3, 3, 4]);
    $unique = $duplicated->unique();
    expect($unique->toArray())->toBe([0 => 1, 1 => 2, 3 => 3, 5 => 4]);
});

it('flattens nested arrays', function () {
    $nested = new Collection([[1, 2], [3, 4], [5, 6]]);
    $flattened = $nested->flatten();
    expect($flattened->toArray())->toBe([1, 2, 3, 4, 5, 6]);
});

it('merges with another collection', function () {
    $other = new Collection([6, 7, 8]);
    $merged = $this->collection->merge($other);
    expect($merged->toArray())->toBe([1, 2, 3, 4, 5, 6, 7, 8]);
});

it('zips with arrays', function () {
    $letters = ['a', 'b', 'c'];
    $numbers = [1, 2, 3];
    
    $collection = new Collection($letters);
    $zipped = $collection->zip($numbers);
    
    expect($zipped->toArray())->toBe([['a', 1], ['b', 2], ['c', 3]]);
});

it('executes callback when condition is true', function () {
    $originalCollection = clone $this->collection;
    $result = $originalCollection->when(true, function ($collection) {
        $collection->map(fn($item) => $item * 2);
    });

    expect($result)->toBeInstanceOf(Collection::class);
});

it('executes callback unless condition is true', function () {
    $originalCollection = clone $this->collection;
    $result = $originalCollection->unless(false, function ($collection) {
        $collection->map(fn($item) => $item * 2);
    });

    expect($result)->toBeInstanceOf(Collection::class);
});

it('creates collection from values', function () {
    $values = $this->userCollection->values();
    expect($values)->toBeInstanceOf(Collection::class);
    expect($values->count())->toBe(3);
});

it('gets keys from collection', function () {
    $assoc = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
    $keys = $assoc->keys();
    expect($keys->toArray())->toBe(['a', 'b', 'c']);
});

it('excludes specified keys', function () {
    $assoc = new Collection(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
    $except = $assoc->except('b', 'd');
    expect($except->toArray())->toBe(['a' => 1, 'c' => 3]);
});

it('gets random items', function () {
    $random = $this->collection->random();
    expect($random)->toBeGreaterThanOrEqual(1);
    expect($random)->toBeLessThanOrEqual(5);

    $randomMultiple = $this->collection->random(3);
    expect($randomMultiple)->toHaveCount(3);
});

it('pops and pushes items', function () {
    $collection = new Collection([1, 2, 3]);
    $popped = $collection->pop();
    expect($popped)->toBe(3);
    expect($collection->toArray())->toBe([1, 2]);

    $collection->push(4, 5);
    expect($collection->toArray())->toBe([1, 2, 4, 5]);
});