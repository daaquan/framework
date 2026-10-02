<?php

use Phare\Collections\Collection;

it('keeps storage operations synchronized with collection transformations', function () {
    $collection = new Collection(['First' => 1]);
    $collection->set('Second', 2);
    $collection['THIRD'] = 3;
    $collection->fourth = 4;
    $collection->push(5);

    expect($collection->get('SECOND'))->toBe(2)
        ->and($collection['third'])->toBe(3)
        ->and($collection->fourth)->toBe(4)
        ->and(count($collection))->toBe(5)
        ->and(iterator_to_array($collection))->toBe($collection->toArray())
        ->and(json_decode($collection->toJson(), true))->toBe($collection->toArray());

    unset($collection['FIRST'], $collection->fourth);
    $collection->remove('second');

    expect($collection->values()->toArray())->toBe([3, 5])
        ->and($collection->keys()->toArray())->toBe(['third', 0]);

    $collection->clear();

    expect($collection->isEmpty())->toBeTrue();
});

it('preserves case-sensitive keys when requested', function () {
    $collection = new Collection(['MixedCase' => 1], false);
    $collection->set('OtherKey', 2);

    expect($collection->get('MixedCase'))->toBe(1)
        ->and($collection->has('mixedcase'))->toBeFalse()
        ->and($collection->toArray())->toBe(['MixedCase' => 1, 'OtherKey' => 2]);
});

it('distinguishes stored null and falsy values from missing keys', function () {
    $collection = new Collection(['Null' => null, 'False' => false, 'Zero' => 0, 'Empty' => '']);

    foreach (['FALSE' => false, 'ZERO' => 0, 'EMPTY' => ''] as $key => $value) {
        expect($collection->has($key))->toBeTrue()
            ->and(isset($collection[$key]))->toBeTrue()
            ->and(isset($collection->{$key}))->toBeTrue()
            ->and($collection->get($key, 'fallback'))->toBe($value);
    }

    expect($collection->has('NULL'))->toBeTrue()
        ->and(isset($collection['NULL']))->toBeTrue()
        ->and(isset($collection->NULL))->toBeTrue()
        ->and($collection->get('NULL'))->toBeNull()
        ->and($collection->get('NULL', 'fallback'))->toBe('fallback')
        ->and($collection->has('missing'))->toBeFalse()
        ->and(isset($collection['missing']))->toBeFalse()
        ->and(isset($collection->missing))->toBeFalse()
        ->and($collection->get('missing', 'fallback'))->toBe('fallback');
});

it('pushes after explicitly assigned numeric offsets without overwriting keys', function () {
    $collection = new Collection([2 => 'first', 'Label' => 'named']);
    $collection[5] = 'second';
    $collection->push('third');

    expect($collection->toArray())->toBe([2 => 'first', 'Label' => 'named', 5 => 'second', 6 => 'third'])
        ->and(iterator_to_array($collection))->toBe($collection->toArray());
});

it('merges initialized storage while keeping case-insensitive lookup', function () {
    $collection = new Collection(['Old' => 1]);
    $collection->init(['New' => 2]);
    $collection->set('THIRD', 3);

    expect($collection->toArray())->toBe(['Old' => 1, 'New' => 2, 'THIRD' => 3])
        ->and($collection->has('old'))->toBeTrue()
        ->and($collection->get('NEW'))->toBe(2)
        ->and(count($collection))->toBe(3);
});

it('keeps collection-returning replacements independent of their source', function () {
    $source = new Collection(['first' => 1, 'second' => 2]);
    $replacement = $source->replace(['second' => 20, 'third' => 3]);
    $replacement->set('fourth', 4);

    expect($replacement)->toBeInstanceOf(Collection::class)
        ->and($replacement->keys()->toArray())->toBe(['first', 'second', 'third', 'fourth'])
        ->and($replacement->values()->toArray())->toBe([1, 20, 3, 4])
        ->and($source->toArray())->toBe(['first' => 1, 'second' => 2]);
});

it('exposes in-place transformations through delegated reads and serialization', function () {
    $collection = new Collection(['first' => 1, 'second' => 2]);
    expect($collection->transform(fn ($value) => $value * 10))->toBe($collection);
    $collection->set('third', 30);

    expect($collection->get('FIRST'))->toBe(10)
        ->and($collection['SECOND'])->toBe(20)
        ->and($collection->third)->toBe(30)
        ->and(iterator_to_array($collection))->toBe(['first' => 10, 'second' => 20, 'third' => 30])
        ->and(json_decode(json_encode($collection), true))->toBe($collection->toArray());
});

it('keeps cloned collections independent after both storage and collection mutations', function () {
    $original = new Collection(['first' => 1]);
    $clone = clone $original;
    $clone->set('second', 2);
    $original->push(3);

    expect($clone->toArray())->toBe(['first' => 1, 'second' => 2])
        ->and($original->toArray())->toBe(['first' => 1, 3]);
});

it('preserves data when an unknown delegated operation fails', function () {
    $collection = new Collection(['first' => 1]);

    expect(fn () => $collection->unknownStorageOperation())->toThrow(Error::class);

    $collection->set('second', 2);
    expect($collection->toArray())->toBe(['first' => 1, 'second' => 2]);
});
