<?php

use Phalcon\Mvc\Model\Criteria;
use Phare\Eloquent\Builder;

it('can create a new instance', function () {
    $builder = new Builder();

    expect($builder)->toBeInstanceOf(Builder::class);
    expect($builder)->toBeInstanceOf(Criteria::class);
});

it('returns the first record', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);

    // Assuming the "get" method returns a mock query
    $builder->get()->getFirst();

    expect($builder->first())->toBeInstanceOf(\Phalcon\Mvc\ModelInterface::class);
});

it('returns an instance of self on where condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->where('field', '=', 'value');

    expect($response)->toBeInstanceOf(Builder::class);
});

it('throws an exception if the operator is invalid', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);

    $builder->where('field', 'invalid', 'value')->get()->getFirst();
})
    ->expectException(\Phalcon\Mvc\Model\Exception::class)
    ->expectExceptionMessageMatches('/Syntax error, unexpected token IDENTIFIER\(invalid\).*/');

it('returns an instance of self on andWhere condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->andWhere('field', '=', 'value');

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on orWhere condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->orWhere('field', '=', 'value');

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on whereIn condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->whereIn('field', ['value1', 'value2']);

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on orWhereIn condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->orWhereIn('field', ['value1', 'value2']);

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on whereNotIn condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->whereNotIn('field', ['value1', 'value2']);

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on whereLike condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->whereLike('field', '%value%');

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on whereNotLike condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->whereNotLike('field', '%value%');

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on whereBetween condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->whereBetween('field', 1, 10);

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on whereNotBetween condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->whereNotBetween('field', 1, 10);

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on whereNull condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->whereNull('field');

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on whereNotNull condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->whereNotNull('field');

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on columns condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->columns(['field1', 'field2']);

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on orderBy condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->orderBy('field', 'desc');

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on limit condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->limit(10, 0);

    expect($response)->toBeInstanceOf(Builder::class);
});

it('returns an instance of self on groupBy condition', function () {
    $builder = (new Builder())->setModelName(\Tests\Mock\Models\User::class);
    $response = $builder->groupBy('field');

    expect($response)->toBeInstanceOf(Builder::class);
});
