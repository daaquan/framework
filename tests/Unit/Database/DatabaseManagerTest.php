<?php

use Mockery as m;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Application;
use Phare\Database\MySql\DatabaseManager;
use Phare\Events\Dispatcher;

class FakeTransaction
{
    public int $commitCount = 0;

    public int $rollbackCount = 0;

    public function begin(): bool
    {
        return true;
    }

    public function commit(): void
    {
        $this->commitCount++;
    }

    public function rollback(): void
    {
        $this->rollbackCount++;
    }
}

class FakeTransactionManager
{
    public function __construct(private FakeTransaction $transaction) {}

    public function get(): FakeTransaction
    {
        return $this->transaction;
    }
}

class TestableDatabaseManager extends DatabaseManager
{
    protected array $fakeTransactions = [];

    public function resolveSqlitePathForTest(array $config): string
    {
        return $this->resolveSqlitePath($config);
    }

    public function inTransactionState(): bool
    {
        return $this->inTransaction();
    }

    public function beginTransactionOnSchema(string $schema): object
    {
        $tx = new FakeTransaction();
        $this->fakeTransactions[$schema] = $tx;

        return new FakeTransactionManager($tx);
    }

    public function transactionFor(string $schema): ?FakeTransaction
    {
        return $this->fakeTransactions[$schema] ?? null;
    }
}

class TestDatabaseApplication extends Container implements Application
{
    public function version(): string
    {
        return 'test';
    }

    public function basePath(string $path = ''): string
    {
        return '/tmp'.$path;
    }

    public function environment(...$environments)
    {
        return 'testing';
    }

    public function runningInConsole()
    {
        return true;
    }

    public function runningUnitTests()
    {
        return true;
    }

    public function bootstrapWith(array $bootstrappers)
    {
        return null;
    }
}

afterEach(function () {
    m::close();
});

it('builds sqlite paths inside the database directory for relative names', function () {
    $app = m::mock(Application::class);
    $app->shouldReceive('databasePath')
        ->once()
        ->with('testing.sqlite')
        ->andReturn('/tmp/database/testing.sqlite');

    $manager = new TestableDatabaseManager($app, []);
    $path = $manager->resolveSqlitePathForTest(['database' => 'testing']);

    expect($path)->toBe('/tmp/database/testing.sqlite');
});

it('respects sqlite file names that already include the extension', function () {
    $app = m::mock(Application::class);
    $app->shouldReceive('databasePath')
        ->once()
        ->with('example.sqlite')
        ->andReturn('/tmp/database/example.sqlite');

    $manager = new TestableDatabaseManager($app, []);
    $path = $manager->resolveSqlitePathForTest(['database' => 'example.sqlite']);

    expect($path)->toBe('/tmp/database/example.sqlite');
});

it('accepts absolute sqlite paths verbatim', function () {
    $absolutePath = '/var/tmp/custom.sqlite';

    $app = m::mock(Application::class);
    $app->shouldReceive('databasePath')->never();

    $manager = new TestableDatabaseManager($app, []);
    $path = $manager->resolveSqlitePathForTest(['database' => $absolutePath]);

    expect($path)->toBe($absolutePath);
});

it('supports in-memory sqlite databases without touching the filesystem', function () {
    $app = m::mock(Application::class);
    $app->shouldReceive('databasePath')->never();

    $manager = new TestableDatabaseManager($app, []);
    $path = $manager->resolveSqlitePathForTest(['database' => ':memory:']);

    expect($path)->toBe(':memory:');
});

it('runs after commit callbacks when transactions complete successfully', function () {
    $app = new TestableDatabaseManager(new TestDatabaseApplication(), []);
    $called = 0;

    $app->startTransactions(['db']);
    $app->addCallback(function () use (&$called) {
        $called++;
    });

    expect($called)->toBe(0);
    expect($app->inTransactionState())->toBeTrue();

    $app->finalizeTransactions();
    $app->clearTransactions();

    expect($called)->toBe(1);
    expect($app->inTransactionState())->toBeFalse();
});

it('dispatches database transaction lifecycle events', function () {
    $app = new TestDatabaseApplication();
    $dispatcher = new Dispatcher($app);
    $app->singleton('events', fn () => $dispatcher);

    $captured = [];
    $dispatcher->listen(\Phare\Database\Events\TransactionBeginning::class, function ($event) use (&$captured) {
        $captured[] = ['beginning', $event->connectionName];
    });
    $dispatcher->listen(\Phare\Database\Events\TransactionCommitting::class, function ($event) use (&$captured) {
        $captured[] = ['committing', $event->connectionName];
    });
    $dispatcher->listen(\Phare\Database\Events\TransactionCommitted::class, function ($event) use (&$captured) {
        $captured[] = ['committed', $event->connectionName];
    });

    $manager = new TestableDatabaseManager($app, []);
    $manager->startTransactions(['db']);
    $manager->finalizeTransactions();
    $manager->clearTransactions();

    expect($captured)->toBe([
        ['beginning', 'db'],
        ['committing', 'db'],
        ['committed', 'db'],
    ]);
});
