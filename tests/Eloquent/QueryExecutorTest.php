<?php

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    test('query executor tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for query executor tests.');
    });

    return;
}

use Phare\Collections\Collection;
use Phare\Database\Connection;
use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Eloquent\Query\Executor;
use Tests\Mock\Models\User;

beforeEach(function () {
    $this->connection = Connection::wrap($this->app->make('db'));
    $schema = new SchemaBuilder($this->connection);

    $schema->dropIfExists('users');
    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->integer('age')->nullable();
    });

    foreach ([['ann', 30], ['bob', 20], ['cid', 40]] as [$name, $age]) {
        $this->connection->statement(
            'INSERT INTO users (name, email, age) VALUES (?, ?, ?)',
            [$name, "{$name}@example.com", $age]
        );
    }

    $this->executor = new Executor($this->connection);
});

afterEach(function () {
    (new SchemaBuilder($this->connection))->dropIfExists('users');
});

it('returns a collection of hydrated models', function () {
    $results = $this->executor->select(new User(), []);

    expect($results)->toBeInstanceOf(Collection::class)
        ->toHaveCount(3)
        ->and($results->first())->toBeInstanceOf(User::class)
        ->and($results->first()->name)->toBe('ann');
});

it('hydrates models with their original state synced', function () {
    $user = $this->executor->select(new User(), [])->first();

    expect($user->getDirty())->toBe([])
        ->and($user->getKey())->not->toBeNull();
});

it('applies conditions with bindings', function () {
    $results = $this->executor->select(new User(), [
        'conditions' => 'age > :age_0:',
        'bind' => ['age_0' => 25],
    ]);

    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->toArray())->toBe(['ann', 'cid']);
});

it('applies order and limit', function () {
    $results = $this->executor->select(new User(), [
        'order' => 'age DESC',
        'limit' => 2,
    ]);

    expect($results->pluck('name')->toArray())->toBe(['cid', 'ann']);
});

it('returns an empty collection when nothing matches', function () {
    $results = $this->executor->select(new User(), [
        'conditions' => 'name = :name_0:',
        'bind' => ['name_0' => 'nobody'],
    ]);

    expect($results)->toBeInstanceOf(Collection::class)
        ->toHaveCount(0)
        ->and($results->first())->toBeNull();
});

it('counts rows honouring the conditions', function () {
    expect($this->executor->count(new User(), []))->toBe(3)
        ->and($this->executor->count(new User(), [
            'conditions' => 'age > :age_0:',
            'bind' => ['age_0' => 25],
        ]))->toBe(2);
});

it('is iterable', function () {
    $names = [];

    foreach ($this->executor->select(new User(), ['order' => 'name ASC']) as $user) {
        $names[] = $user->name;
    }

    expect($names)->toBe(['ann', 'bob', 'cid']);
});
