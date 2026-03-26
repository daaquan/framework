<?php

use Phare\Eloquent\Model;

class FillableMassAssignmentModel extends Model
{
    protected array $fillable = ['name'];
}

class GuardedMassAssignmentModel extends Model
{
    protected array $guarded = ['password'];
}

class TotallyGuardedMassAssignmentModel extends Model {}

it('allows listed fillable attributes', function () {
    $model = new FillableMassAssignmentModel();
    $model->fill([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);

    expect($model->getAttributes())->toBe(['name' => 'Alice'])
        ->and($model->isFillable('name'))->toBeTrue()
        ->and($model->isFillable('email'))->toBeFalse();
});

it('blocks guarded attributes', function () {
    $model = new GuardedMassAssignmentModel();
    $model->fill([
        'name' => 'Alice',
        'password' => 'secret',
    ]);

    expect($model->getAttributes())->toBe(['name' => 'Alice'])
        ->and($model->isGuarded('password'))->toBeTrue();
});

it('supports unguard and reguard', function () {
    GuardedMassAssignmentModel::unguard();

    $model = new GuardedMassAssignmentModel();
    $model->fill([
        'name' => 'Alice',
        'password' => 'secret',
    ]);

    expect($model->getAttributes())->toBe([
        'name' => 'Alice',
        'password' => 'secret',
    ]);

    GuardedMassAssignmentModel::reguard();

    $model = new GuardedMassAssignmentModel();
    $model->fill([
        'name' => 'Bob',
        'password' => 'hidden',
    ]);

    expect($model->getAttributes())->toBe(['name' => 'Bob']);
});

it('detects totally guarded models', function () {
    $model = new TotallyGuardedMassAssignmentModel();

    expect($model->totallyGuarded())->toBeTrue();
});

it('fillable from array respects guard rules', function () {
    $model = new GuardedMassAssignmentModel();

    expect($model->fillableFromArray([
        'name' => 'Alice',
        'password' => 'secret',
    ]))->toBe(['name' => 'Alice']);
});
