<?php

use Phare\Console\Commands\MakeMigrationCommand;

class TestableMakeMigrationCommand extends MakeMigrationCommand
{
    private array $arguments = [];

    private array $options = [];

    private array $messages = [];

    private string $basePath = '';

    public function setBasePath(string $path): void
    {
        $this->basePath = $path;
    }

    public function setArgument(string $key, mixed $value): void
    {
        $this->arguments[$key] = $value;
    }

    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function argument(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->arguments;
        }

        return $this->arguments[$key] ?? null;
    }

    public function option(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->options;
        }

        return $this->options[$key] ?? null;
    }

    public function info(string $message): void
    {
        $this->messages[] = $message;
    }

    public function error(string $message): void
    {
        $this->messages[] = 'ERROR: ' . $message;
    }

    protected function getMigrationFileName(string $name): string
    {
        return '2023_01_01_120000_' . $name . '.php';
    }

    protected function migrationDirectory(): string
    {
        return $this->basePath . '/migrations';
    }
}

beforeEach(function () {
    $this->testMigrationPath = sys_get_temp_dir() . '/test_make_migration_' . bin2hex(random_bytes(4));
    mkdir($this->testMigrationPath, 0755, true);
});

afterEach(function () {
    if (!is_dir($this->testMigrationPath)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->testMigrationPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($this->testMigrationPath);
});

it('can create basic migration', function () {
    $command = new TestableMakeMigrationCommand();
    $command->setBasePath($this->testMigrationPath);
    $command->setArgument('name', 'create_posts_table');

    $result = $command->handle();

    expect($result)->toBe(0);
    expect($command->getMessages())->toContain('Migration 2023_01_01_120000_create_posts_table.php created successfully.');

    $expectedFile = $this->testMigrationPath . '/migrations/2023_01_01_120000_create_posts_table.php';
    expect(file_exists($expectedFile))->toBeTrue();

    $content = file_get_contents($expectedFile);
    expect($content)->toContain('class extends Migration');
    expect($content)->toContain('public function up()');
    expect($content)->toContain('public function down()');
});

it('can create migration with create table option', function () {
    $command = new TestableMakeMigrationCommand();
    $command->setBasePath($this->testMigrationPath);
    $command->setArgument('name', 'create_users_table');
    $command->setOption('create', 'users');

    $result = $command->handle();

    expect($result)->toBe(0);

    $expectedFile = $this->testMigrationPath . '/migrations/2023_01_01_120000_create_users_table.php';
    expect(file_exists($expectedFile))->toBeTrue();

    $content = file_get_contents($expectedFile);
    expect($content)->toContain("create('users'");
    expect($content)->toContain('$table->id()');
    expect($content)->toContain('$table->timestamps()');
    expect($content)->toContain("dropIfExists('users')");
});

it('can create migration with table modification option', function () {
    $command = new TestableMakeMigrationCommand();
    $command->setBasePath($this->testMigrationPath);
    $command->setArgument('name', 'add_email_to_users');
    $command->setOption('table', 'users');

    $result = $command->handle();

    expect($result)->toBe(0);

    $expectedFile = $this->testMigrationPath . '/migrations/2023_01_01_120000_add_email_to_users.php';
    expect(file_exists($expectedFile))->toBeTrue();

    $content = file_get_contents($expectedFile);
    expect($content)->toContain("table('users'");
    expect($content)->not->toContain("create('users'");
    expect($content)->not->toContain("dropIfExists('users')");
});

it('prevents creating duplicate migration files', function () {
    $dir = $this->testMigrationPath . '/migrations';
    mkdir($dir, 0755, true);
    $existingFile = $dir . '/2023_01_01_120000_create_posts_table.php';
    file_put_contents($existingFile, '<?php // existing file');

    $command = new TestableMakeMigrationCommand();
    $command->setBasePath($this->testMigrationPath);
    $command->setArgument('name', 'create_posts_table');

    $result = $command->handle();

    expect($result)->toBe(1);
    expect($command->getMessages())->toContain('ERROR: Migration 2023_01_01_120000_create_posts_table.php already exists!');
});

it('creates migration directory if it does not exist', function () {
    $command = new TestableMakeMigrationCommand();
    $command->setBasePath($this->testMigrationPath . '/new_migrations');
    $command->setArgument('name', 'create_test_table');

    $result = $command->handle();

    expect($result)->toBe(0);
    expect(is_dir($this->testMigrationPath . '/new_migrations/migrations'))->toBeTrue();
});
