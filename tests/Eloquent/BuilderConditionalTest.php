<?php

use Phare\Eloquent\Builder;

it('when() runs the callback when the condition is truthy', function () {
    $builder = new Builder();
    $result = $builder->when(true, function (Builder $query) {
        $query->where('active', 1);
    });

    expect($result)->toBeInstanceOf(Builder::class)
        ->and($builder->getParams()['conditions'])->toContain('active');
});

it('when() skips the callback when the condition is falsy', function () {
    $builder = new Builder();
    $builder->when(false, function (Builder $query) {
        $query->where('active', 1);
    });

    expect($builder->getParams()['conditions'] ?? null)->toBeNull();
});

it('when() runs the default callback when the condition is falsy', function () {
    $builder = new Builder();
    $builder->when(
        false,
        fn (Builder $query) => $query->where('active', 1),
        fn (Builder $query) => $query->where('archived', 1),
    );

    expect($builder->getParams()['conditions'])->toContain('archived');
});

it('when() passes the resolved value to the callback', function () {
    $builder = new Builder();
    $seen = null;
    $builder->when('hello', function (Builder $query, $value) use (&$seen) {
        $seen = $value;
    });

    expect($seen)->toBe('hello');
});

it('when() resolves a Closure condition against the builder', function () {
    $builder = new Builder();
    $builder->when(
        fn (Builder $query) => true,
        fn (Builder $query) => $query->where('x', 1),
    );

    expect($builder->getParams()['conditions'])->toContain('x');
});

it('unless() runs the callback when the condition is falsy', function () {
    $builder = new Builder();
    $builder->unless(false, fn (Builder $query) => $query->where('y', 1));

    expect($builder->getParams()['conditions'])->toContain('y');
});

it('unless() skips the callback when the condition is truthy', function () {
    $builder = new Builder();
    $builder->unless(true, fn (Builder $query) => $query->where('y', 1));

    expect($builder->getParams()['conditions'] ?? null)->toBeNull();
});

it('tap() invokes the callback and returns the builder', function () {
    $builder = new Builder();
    $tapped = null;
    $result = $builder->tap(function (Builder $query) use (&$tapped) {
        $tapped = $query;
    });

    expect($result)->toBe($builder)
        ->and($tapped)->toBe($builder);
});
