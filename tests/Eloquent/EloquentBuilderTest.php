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

it('generates unique bind keys for duplicate field names', function () {
    $builder = new Builder();
    $builder->where('status', 'active')->where('status', '!=', 'banned');

    $params = $builder->getParams();

    // Both bind keys should exist (no overwrite)
    expect($params['bind'])->toHaveCount(2);
    expect($params['bind'])->toHaveKey('status_0');
    expect($params['bind'])->toHaveKey('status_1');
    expect($params['bind']['status_0'])->toBe('active');
    expect($params['bind']['status_1'])->toBe('banned');

    // Conditions should reference both unique keys
    expect($params['conditions'])->toContain(':status_0:');
    expect($params['conditions'])->toContain(':status_1:');
});

it('generates unique bind keys for three conditions on same field', function () {
    $builder = new Builder();
    $builder->where('price', '>', 10)
        ->where('price', '<', 100)
        ->where('price', '!=', 50);

    $params = $builder->getParams();

    expect($params['bind'])->toHaveCount(3);
    expect($params['bind']['price_0'])->toBe(10);
    expect($params['bind']['price_1'])->toBe(100);
    expect($params['bind']['price_2'])->toBe(50);
});

it('whereRaw does not overwrite existing conditions', function () {
    $builder = new Builder();
    $builder->where('status', 'active')
        ->whereRaw('score > ?', [50]);

    $params = $builder->getParams();

    expect($params['conditions'])->toContain('status');
    expect($params['conditions'])->toContain('score > ?');
    expect($params['conditions'])->toContain('AND');
});

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
