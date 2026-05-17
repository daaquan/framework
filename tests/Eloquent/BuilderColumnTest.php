<?php

use Phare\Eloquent\Builder;

it('whereColumn() compares two columns with an explicit operator', function () {
    $builder = new Builder();
    $response = $builder->whereColumn('updated_at', '>', 'created_at');

    expect($response)->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['conditions'])->toContain('updated_at > created_at');
});

it('whereColumn() defaults to equality when no operator is given', function () {
    $builder = new Builder();
    $builder->whereColumn('first_name', 'last_name');

    expect($builder->getParams()['conditions'])->toContain('first_name = last_name');
});

it('whereColumn() does not register a bind parameter', function () {
    $builder = new Builder();
    $builder->whereColumn('a', 'b');

    expect($builder->getParams()['bind'] ?? [])->toBe([]);
});

it('orWhereColumn() compares two columns as an OR condition', function () {
    $builder = new Builder();
    $builder->orWhereColumn('x', 'y');

    // On a fresh builder there is no left-hand side to OR against, so the
    // condition is emitted bare rather than as a malformed "() OR (...)".
    expect($builder->getParams()['conditions'])->toBe('x = y');
});

it('orWhereColumn() ORs against an existing condition', function () {
    $builder = new Builder();
    $builder->whereColumn('a', 'b')->orWhereColumn('x', 'y');

    expect($builder->getParams()['conditions'])->toBe('(a = b) OR (x = y)');
});

it('orderByRaw() sets a raw ordering expression', function () {
    $builder = new Builder();
    $response = $builder->orderByRaw('name ASC, id DESC');

    expect($response)->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['order'])->toBe('name ASC, id DESC');
});
