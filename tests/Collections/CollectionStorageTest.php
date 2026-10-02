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
