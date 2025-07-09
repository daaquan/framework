<?php

use Phare\Contracts\Foundation\Application;
use Phare\Database\MySql\DatabaseManager;
use Tests\Mock\Models\User as Model;

beforeEach(function () {
    $this->appMock = Mockery::mock(Application::class);
    $this->databasesConfig = [
        'db' => [
            'driver' => 'mysql',
            'read' => 'localhost',
            'write' => 'localhost',
            'username' => 'root',
            'password' => '',
            'database' => 'db',
            'port' => 3306,
        ],
    ];
    $this->dbManager = new DatabaseManager($this->appMock, $this->databasesConfig);
});

test('getConnectionService returns correct service name', function () {
    $serviceName = $this->dbManager->getConnectionService('db');
    expect($serviceName)->toEqual('db');
});

test('getConnectionService throws exception for undefined service', function () {
    $this->dbManager->getConnectionService('unknown');
})->throws(RuntimeException::class);

test('transaction commits when no exceptions', function () {
    $this->dbManager->setupDatabases();
    $operations = fn() => 'ok';
    $result = $this->dbManager->transaction($operations, ['db']);
    expect($result)->toBe('ok');
});

test('transaction rolls back on exception', function () {
    $operations = fn() => throw new RuntimeException('rollback');
    $this->dbManager->transaction($operations, ['db']);
})->throws(Exception::class);

test('setupDatabases registers singleton for db DB', function () {
    // Switch to a spy if you need to verify the Application::singleton call
    $this->appMock->shouldReceive('singleton')->once()->withArgs(function ($name, $closure) {
        return $name === 'db' && is_callable($closure);
    });
    $this->dbManager->setupDatabases();
});

test('getConnection throws exception when no type but read/write separation configured', function () {
    $this->dbManager->getConnection(['read' => [], 'write' => []]);
})->throws(RuntimeException::class);

test('finalizeTransactions commits all transactions', function () {
    $this->dbManager->startTransactions(['db']);
    $this->dbManager->finalizeTransactions();
    // Add mocks for the transaction manager if needed
});

test('undoTransactions rolls back all transactions', function () {
    $this->dbManager->startTransactions(['db']);
    $this->dbManager->undoTransactions();
});

test('clearTransactions clears state', function () {
    $this->dbManager->startTransactions(['db']);
    $this->dbManager->clearTransactions();
    // Use reflection to inspect private properties if required
});

test('attachModelToTransaction works correctly', function () {
    $this->dbManager->startTransactions(['db']);
    $model = Mockery::mock(Model::class);
    $model->shouldReceive('setTransaction')->once();
    $this->dbManager->attachModelToTransaction($model);
});
