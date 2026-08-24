<?php

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    test('connection tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for connection tests.');
    });

    return;
}

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phare\Database\Connection;

describe('Connection', function () {
    beforeEach(function () {
        $this->adapter = $this->app->make('db');
        $this->connection = new Connection($this->adapter);

        $this->connection->statement('DROP TABLE IF EXISTS conn_test');
        $this->connection->statement('CREATE TABLE conn_test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    });

    afterEach(function () {
        $this->connection->statement('DROP TABLE IF EXISTS conn_test');
    });

    it('reports the driver name in lower case', function () {
        expect($this->connection->getDriverName())->toBe('sqlite');
    });

    it('runs a statement with bindings and reads rows back as assoc arrays', function () {
        $this->connection->statement('INSERT INTO conn_test (name) VALUES (?)', ['alice']);
        $this->connection->statement('INSERT INTO conn_test (name) VALUES (?)', ['bob']);

        $rows = $this->connection->select('SELECT name FROM conn_test ORDER BY id');

        expect($rows)->toBe([['name' => 'alice'], ['name' => 'bob']]);
    });

    it('selectOne returns a single assoc row', function () {
        $this->connection->statement('INSERT INTO conn_test (name) VALUES (?)', ['alice']);

        expect($this->connection->selectOne('SELECT name FROM conn_test WHERE name = ?', ['alice']))
            ->toBe(['name' => 'alice']);
    });

    it('selectOne returns null when nothing matches', function () {
        expect($this->connection->selectOne('SELECT name FROM conn_test WHERE name = ?', ['nobody']))
            ->toBeNull();
    });

    it('exposes the last insert id', function () {
        $this->connection->statement('INSERT INTO conn_test (name) VALUES (?)', ['alice']);

        expect((int)$this->connection->lastInsertId())->toBeGreaterThan(0);
    });

    it('commits a transaction', function () {
        $this->connection->beginTransaction();
        $this->connection->statement('INSERT INTO conn_test (name) VALUES (?)', ['kept']);
        $this->connection->commit();

        expect($this->connection->select('SELECT name FROM conn_test'))->toBe([['name' => 'kept']]);
    });

    it('rolls a transaction back', function () {
        $this->connection->beginTransaction();
        $this->connection->statement('INSERT INTO conn_test (name) VALUES (?)', ['dropped']);
        $this->connection->rollBack();

        expect($this->connection->select('SELECT name FROM conn_test'))->toBe([]);
    });

    it('exposes the underlying phalcon adapter as an escape hatch', function () {
        expect($this->connection->getAdapter())
            ->toBeInstanceOf(AbstractPdo::class)
            ->toBe($this->adapter);
    });

    it('wraps a raw phalcon adapter', function () {
        expect(Connection::wrap($this->adapter))->toBeInstanceOf(Connection::class);
    });

    it('returns an already-wrapped connection unchanged', function () {
        expect(Connection::wrap($this->connection))->toBe($this->connection);
    });
});
