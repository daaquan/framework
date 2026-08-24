<?php

use Phare\Eloquent\Query\Compiler;

function compile(array $params, string $table = 'users'): array
{
    return (new Compiler())->compileSelect($table, $params);
}

it('compiles a bare select', function () {
    expect(compile([]))->toBe(['SELECT * FROM users', []]);
});

it('compiles an explicit column list given as a string', function () {
    expect(compile(['columns' => 'id,name']))->toBe(['SELECT id,name FROM users', []]);
});

it('compiles an explicit column list given as an array', function () {
    expect(compile(['columns' => ['id', 'name']]))->toBe(['SELECT id, name FROM users', []]);
});

it('drops a star column list back to a bare select', function () {
    expect(compile(['columns' => ['*']]))->toBe(['SELECT * FROM users', []]);
});

it('rewrites phalcon bind placeholders into pdo named parameters', function () {
    expect(compile([
        'conditions' => 'name = :name_0: AND age > :age_1:',
        'bind' => ['name_0' => 'alice', 'age_1' => 30],
    ]))->toBe([
        'SELECT * FROM users WHERE name = :name_0 AND age > :age_1',
        ['name_0' => 'alice', 'age_1' => 30],
    ]);
});

it('leaves a condition without placeholders alone', function () {
    expect(compile(['conditions' => 'deleted_at IS NULL']))
        ->toBe(['SELECT * FROM users WHERE deleted_at IS NULL', []]);
});

it('compiles group by from a string and from an array', function () {
    expect(compile(['group' => 'role']))->toBe(['SELECT * FROM users GROUP BY role', []]);
    expect(compile(['group' => ['role', 'team']]))->toBe(['SELECT * FROM users GROUP BY role, team', []]);
});

it('compiles order by', function () {
    expect(compile(['order' => 'name DESC']))->toBe(['SELECT * FROM users ORDER BY name DESC', []]);
});

it('compiles a scalar limit', function () {
    expect(compile(['limit' => 10]))->toBe(['SELECT * FROM users LIMIT 10', []]);
});

it('compiles a limit with an offset', function () {
    expect(compile(['limit' => ['number' => 10, 'offset' => 20]]))
        ->toBe(['SELECT * FROM users LIMIT 10 OFFSET 20', []]);
});

it('ignores a zero offset', function () {
    expect(compile(['limit' => ['number' => 5, 'offset' => 0]]))
        ->toBe(['SELECT * FROM users LIMIT 5', []]);
});

it('rejects a non numeric limit rather than interpolating it', function () {
    expect(fn () => compile(['limit' => '10; DROP TABLE users']))
        ->toThrow(InvalidArgumentException::class);
});

it('compiles every clause together in sql order', function () {
    expect(compile([
        'columns' => ['id'],
        'conditions' => 'role = :role_0:',
        'bind' => ['role_0' => 'admin'],
        'group' => 'team',
        'order' => 'id ASC',
        'limit' => ['number' => 3, 'offset' => 6],
    ]))->toBe([
        'SELECT id FROM users WHERE role = :role_0 GROUP BY team ORDER BY id ASC LIMIT 3 OFFSET 6',
        ['role_0' => 'admin'],
    ]);
});

it('only passes through bindings the conditions actually reference', function () {
    expect(compile([
        'conditions' => 'name = :name_0:',
        'bind' => ['name_0' => 'alice', 'stale_9' => 'unused'],
    ]))->toBe(['SELECT * FROM users WHERE name = :name_0', ['name_0' => 'alice']]);
});

it('compiles a count query', function () {
    expect((new Compiler())->compileCount('users', [
        'conditions' => 'role = :role_0:',
        'bind' => ['role_0' => 'admin'],
    ]))->toBe([
        'SELECT COUNT(*) AS aggregate FROM users WHERE role = :role_0',
        ['role_0' => 'admin'],
    ]);
});

it('drops order and columns from a count query but keeps limit', function () {
    expect((new Compiler())->compileCount('users', ['columns' => ['id'], 'order' => 'id ASC', 'limit' => 5]))
        ->toBe(['SELECT COUNT(*) AS aggregate FROM users LIMIT 5', []]);
});
