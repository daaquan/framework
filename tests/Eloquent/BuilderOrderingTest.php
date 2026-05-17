<?php

use Phare\Eloquent\Builder;

it('latest() orders by the given column descending', function () {
    $builder = new Builder();
    $response = $builder->latest('updated_at');

    expect($response)->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['order'])->toBe('updated_at desc');
});

it('latest() defaults to the created_at column', function () {
    $builder = new Builder();
    $builder->latest();

    expect($builder->getParams()['order'])->toBe('created_at desc');
});

it('oldest() orders by the given column ascending', function () {
    $builder = new Builder();
    $response = $builder->oldest('updated_at');

    expect($response)->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['order'])->toBe('updated_at asc');
});

it('oldest() defaults to the created_at column', function () {
    $builder = new Builder();
    $builder->oldest();

    expect($builder->getParams()['order'])->toBe('created_at asc');
});
