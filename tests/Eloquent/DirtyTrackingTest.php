<?php

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    test('eloquent dirty tracking tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for eloquent dirty tracking tests.');
    });

    return;
}

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Eloquent\Model;

class DirtyTrackingModel extends Model
{
    protected ?string $connection = 'db';

    protected ?string $table = 'dirty_tracking_models';

    protected array $fillable = ['name', 'email'];
}

beforeEach(function () {
    $connection = $this->app->make('db');
    $schema = new SchemaBuilder($connection);

    if ($schema->hasTable('dirty_tracking_models')) {
        $connection->execute('DROP TABLE dirty_tracking_models');
    }

    $schema->create('dirty_tracking_models', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
    });
});

it('is clean on a fresh model', function () {
    $model = new DirtyTrackingModel();

    expect($model->isClean())->toBeTrue()
        ->and($model->isDirty())->toBeFalse()
        ->and($model->getDirty())->toBe([]);
});

it('tracks dirty attributes after changes', function () {
    $model = new DirtyTrackingModel();
    $model->fill(['name' => 'Alice']);
    $model->syncOriginal();
    $model->email = 'alice@example.com';

    expect($model->isDirty())->toBeTrue()
        ->and($model->isDirty('email'))->toBeTrue()
        ->and($model->isClean('name'))->toBeTrue()
        ->and($model->getDirty())->toBe(['email' => 'alice@example.com']);
});

it('records changes after save', function () {
    $model = new DirtyTrackingModel();
    $model->fill(['name' => 'Alice', 'email' => 'a@example.com']);
    $model->create();

    $model->email = 'b@example.com';
    $model->save();

    expect($model->wasChanged())->toBeTrue()
        ->and($model->wasChanged('email'))->toBeTrue()
        ->and($model->getChanges()['email'])->toBe('b@example.com')
        ->and($model->isClean())->toBeTrue();
});

it('sync original resets dirty state', function () {
    $model = new DirtyTrackingModel();
    $model->fill(['name' => 'Alice']);

    expect($model->isDirty())->toBeTrue();

    $model->syncOriginal();

    expect($model->isClean())->toBeTrue()
        ->and($model->getDirty())->toBe([]);
});
