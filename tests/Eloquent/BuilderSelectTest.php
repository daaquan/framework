<?php

use Phare\Eloquent\Builder;

it('select() accepts column names as variadic arguments', function () {
    $builder = new Builder();
    $response = $builder->select('name', 'email');

    expect($response)->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['columns'])->toBe('name,email');
});

it('select() accepts an array of columns', function () {
    $builder = new Builder();
    $builder->select(['id', 'name']);

    expect($builder->getParams()['columns'])->toBe('id,name');
});

it('select() defaults to all columns', function () {
    $builder = new Builder();
    $builder->select();

    expect($builder->getParams()['columns'])->toBe('*');
});

it('addSelect() appends to a previous select()', function () {
    $builder = new Builder();
    $builder->select('name')->addSelect('email');

    expect($builder->getParams()['columns'])->toBe('name,email');
});

it('addSelect() appends multiple columns', function () {
    $builder = new Builder();
    $builder->select('id')->addSelect('name', 'email');

    expect($builder->getParams()['columns'])->toBe('id,name,email');
});

it('addSelect() behaves like select() when nothing was selected yet', function () {
    $builder = new Builder();
    $builder->addSelect('email');

    expect($builder->getParams()['columns'])->toBe('email');
});
