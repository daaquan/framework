<?php

use Phare\Console\Commands\MigrateCommand;

it('can instantiate migrate command', function () {
    $command = new MigrateCommand();

    expect($command)->toBeInstanceOf(MigrateCommand::class);
});

it('has expected command metadata', function () {
    $command = new MigrateCommand();

    $reflection = new ReflectionClass($command);
    $signature = $reflection->getProperty('signature');
    $signature->setAccessible(true);
    $description = $reflection->getProperty('description');
    $description->setAccessible(true);

    expect($signature->getValue($command))->toContain('migrate');
    expect($description->getValue($command))->toBe('Run database migrations');
});

class FakeMigrator
{
    public array $runResult = [];

    public array $resetResult = [];

    public array $rollbackResult = [];

    public array $receivedPaths = [];

    public ?int $receivedRollbackSteps = null;

    public function run(array $paths): array
    {
        $this->receivedPaths = $paths;

        return $this->runResult;
    }

    public function reset(): array
    {
        return $this->resetResult;
    }

    public function rollback(int $steps): array
    {
        $this->receivedRollbackSteps = $steps;

        return $this->rollbackResult;
    }
}

class FakeConnection
{
    public array $droppedTables = [];

    public function execute(string $sql): void
    {
        $this->droppedTables[] = $sql;
    }
}

class TestableMigrateCommand extends MigrateCommand
{
    private array $options = [];

    private array $messages = [];

    public array $tablesForFresh = [];

    public FakeMigrator $fakeMigrator;

    public FakeConnection $fakeConnection;

    public array $migrationPaths = ['/tmp/migrations'];

    public function option(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->options;
        }

        return $this->options[$key] ?? null;
    }

    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    public function info(string $message): void
    {
        $this->messages[] = $message;
    }

    public function line(string $message = ''): void
    {
        $this->messages[] = $message;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    protected function createMigrator(): object
    {
        return $this->fakeMigrator;
    }

    protected function getConnection(): mixed
    {
        return $this->fakeConnection;
    }

    protected function getMigrationPaths(): array
    {
        return $this->migrationPaths;
    }

    protected function getAllTables($connection): array
    {
        return $this->tablesForFresh;
    }
}

beforeEach(function () {
    $this->migrator = new FakeMigrator();
    $this->connection = new FakeConnection();
    $this->command = new TestableMigrateCommand();
    $this->command->fakeMigrator = $this->migrator;
    $this->command->fakeConnection = $this->connection;
});

it('can run migrations', function () {
    $this->migrator->runResult = ['/tmp/migrations/2026_01_01_000000_create_users_table.php'];

    $result = $this->command->handle();

    expect($result)->toBe(0);
    expect($this->migrator->receivedPaths)->toBe(['/tmp/migrations']);
    expect($this->command->getMessages())->toContain('Running migrations...');
    expect($this->command->getMessages())->toContain('Migrated:');
});

it('can rollback migrations', function () {
    $this->command->setOption('rollback', '2');
    $this->migrator->rollbackResult = ['2026_01_01_000000_create_users_table'];

    $result = $this->command->handle();

    expect($result)->toBe(0);
    expect($this->migrator->receivedRollbackSteps)->toBe(2);
    expect($this->command->getMessages())->toContain('Rolling back 2 migration(s)...');
});

it('can reset migrations', function () {
    $this->command->setOption('reset', true);
    $this->migrator->resetResult = ['2026_01_01_000000_create_users_table'];

    $result = $this->command->handle();

    expect($result)->toBe(0);
    expect($this->command->getMessages())->toContain('Rolling back migrations...');
    expect($this->command->getMessages())->toContain('Rolled back:');
});

it('can refresh migrations', function () {
    $this->command->setOption('refresh', true);
    $this->migrator->resetResult = ['2026_01_01_000000_create_users_table'];
    $this->migrator->runResult = ['/tmp/migrations/2026_01_01_000000_create_users_table.php'];

    $result = $this->command->handle();

    expect($result)->toBe(0);
    expect($this->command->getMessages())->toContain('Rolling back migrations...');
    expect($this->command->getMessages())->toContain('Running migrations...');
});

it('handles fresh option', function () {
    $this->command->setOption('fresh', true);
    $this->command->tablesForFresh = ['users', 'posts'];

    $result = $this->command->handle();

    expect($result)->toBe(0);
    expect($this->connection->droppedTables)->toBe([
        'DROP TABLE IF EXISTS users',
        'DROP TABLE IF EXISTS posts',
    ]);
    expect($this->command->getMessages())->toContain('Dropping all tables...');
    expect($this->command->getMessages())->toContain('Dropped all tables.');
});

it('reports when nothing to migrate', function () {
    $this->migrator->runResult = [];

    $result = $this->command->handle();

    expect($result)->toBe(0);
    expect($this->command->getMessages())->toContain('Nothing to migrate.');
});
