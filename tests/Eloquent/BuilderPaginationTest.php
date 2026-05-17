<?php

use Phare\Eloquent\Builder;

it('orderByDesc() orders by the column descending', function () {
    $builder = new Builder();

    expect($builder->orderByDesc('name'))->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['order'])->toBe('name desc');
});

it('reorder() clears a previously set ordering', function () {
    $builder = new Builder();
    $builder->latest('created_at');
    $builder->reorder();

    expect($builder->getParams()['order'] ?? null)->toBeNull();
});

it('reorder() can clear and set a new ordering', function () {
    $builder = new Builder();
    $builder->latest('created_at');
    $response = $builder->reorder('id', 'desc');

    expect($response)->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['order'])->toBe('id desc');
});

it('forPage() sets only a limit for the first page', function () {
    $builder = new Builder();

    expect($builder->forPage(1, 15))->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['limit'])->toBe(15);
});

it('forPage() sets number and offset for later pages', function () {
    $builder = new Builder();
    $builder->forPage(3, 10);

    expect($builder->getParams()['limit'])->toBe(['number' => 10, 'offset' => 20]);
});

it('forPage() defaults to 15 rows per page', function () {
    $builder = new Builder();
    $builder->forPage(2);

    expect($builder->getParams()['limit'])->toBe(['number' => 15, 'offset' => 15]);
});
