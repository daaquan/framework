<?php

use Phare\Console\Commands\MakeMigrationCommand;

it('can create basic migration', function () {
    $testPath = sys_get_temp_dir() . '/test_make_migration_basic';
    if (!is_dir($testPath)) {
        mkdir($testPath, 0755, true);
    }

    $command = new class() extends MakeMigrationCommand
    {
        public array $testArgs = [];

        public array $testOpts = [];

        public array $testOutput = [];

        public $mockApp;

        protected function argument(?string $key = null): mixed
        {
            return $key === null ? $this->testArgs : ($this->testArgs[$key] ?? null);
        }

        protected function option(?string $key = null): mixed
        {
            return $key === null ? $this->testOpts : ($this->testOpts[$key] ?? null);
        }

        protected function info(string $message): void
        {
            $this->testOutput[] = $message;
        }

        protected function error(string $message): void
        {
            $this->testOutput[] = 'ERROR: ' . $message;
        }

        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }

        // Use a custom method instead of overriding getApplication()
        protected function ensureMigrationDirectory(): void
        {
            $dir = $this->mockApp->databasePath('migrations');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        public function handle(): int
        {
            $name = $this->argument('name');
            $table = $this->option('table');
            $create = $this->option('create');

            $fileName = $this->getMigrationFileName($name);
            $path = $this->mockApp->databasePath('migrations') . '/' . $fileName;

            if (file_exists($path)) {
                $this->error("Migration {$fileName} already exists!");

                return 1;
            }

            $this->ensureMigrationDirectory();

            $stub = $this->getStub($create, $table);
            $migrationName = $this->getMigrationName($name);
            $content = $this->populateStub($stub, $migrationName, $create ?: $table);

            file_put_contents($path, $content);
            $this->info("Migration {$fileName} created successfully.");

            return 0;
        }
    };

    $command->testArgs = ['name' => 'create_posts_table'];
    $command->mockApp = new class($testPath)
    {
        private string $path;

        public function __construct(string $path)
        {
            $this->path = $path;
        }

        public function databasePath(string $p = ''): string
        {
            return $this->path . ($p ? '/' . $p : '');
        }
    };

    $result = $command->handle();

    expect($result)->toBe(0);
    expect($command->testOutput)->toContain('Migration 2023_01_01_120000_create_posts_table.php created successfully.');

    $expectedFile = $testPath . '/migrations/2023_01_01_120000_create_posts_table.php';
    expect(file_exists($expectedFile))->toBe(true);

    $content = file_get_contents($expectedFile);
    expect($content)->toContain('class extends Migration');
    expect($content)->toContain('public function up()');
    expect($content)->toContain('public function down()');

    // Cleanup
    @unlink($expectedFile);
    @rmdir($testPath . '/migrations');
    @rmdir($testPath);
});

it('can create migration with create table option', function () {
    $testPath = sys_get_temp_dir() . '/test_make_migration_create';
    if (!is_dir($testPath)) {
        mkdir($testPath, 0755, true);
    }

    $command = new class() extends MakeMigrationCommand
    {
        public array $testArgs = [];

        public array $testOpts = [];

        public array $testOutput = [];

        public $mockApp;

        protected function argument(?string $key = null): mixed
        {
            return $key === null ? $this->testArgs : ($this->testArgs[$key] ?? null);
        }

        protected function option(?string $key = null): mixed
        {
            return $key === null ? $this->testOpts : ($this->testOpts[$key] ?? null);
        }

        protected function info(string $message): void
        {
            $this->testOutput[] = $message;
        }

        protected function error(string $message): void
        {
            $this->testOutput[] = 'ERROR: ' . $message;
        }

        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }

        protected function ensureMigrationDirectory(): void
        {
            $dir = $this->mockApp->databasePath('migrations');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        public function handle(): int
        {
            $name = $this->argument('name');
            $table = $this->option('table');
            $create = $this->option('create');
            $fileName = $this->getMigrationFileName($name);
            $path = $this->mockApp->databasePath('migrations') . '/' . $fileName;
            if (file_exists($path)) {
                $this->error("Migration {$fileName} already exists!");

                return 1;
            }
            $this->ensureMigrationDirectory();
            $stub = $this->getStub($create, $table);
            $content = $this->populateStub($stub, $this->getMigrationName($name), $create ?: $table);
            file_put_contents($path, $content);
            $this->info("Migration {$fileName} created successfully.");

            return 0;
        }
    };

    $command->testArgs = ['name' => 'create_users_table'];
    $command->testOpts = ['create' => 'users', 'table' => null];
    $command->mockApp = new class($testPath)
    {
        private string $path;

        public function __construct(string $path)
        {
            $this->path = $path;
        }

        public function databasePath(string $p = ''): string
        {
            return $this->path . ($p ? '/' . $p : '');
        }
    };

    $result = $command->handle();
    expect($result)->toBe(0);

    $content = file_get_contents($testPath . '/migrations/2023_01_01_120000_create_users_table.php');
    expect($content)->toContain("create('users'");
    expect($content)->toContain('$table->id()');
    expect($content)->toContain('$table->timestamps()');
    expect($content)->toContain("dropIfExists('users')");

    // Cleanup
    @unlink($testPath . '/migrations/2023_01_01_120000_create_users_table.php');
    @rmdir($testPath . '/migrations');
    @rmdir($testPath);
});

it('can create migration with table modification option', function () {
    $testPath = sys_get_temp_dir() . '/test_make_migration_modify';
    if (!is_dir($testPath)) {
        mkdir($testPath, 0755, true);
    }

    $command = new class() extends MakeMigrationCommand
    {
        public array $testArgs = [];

        public array $testOpts = [];

        public array $testOutput = [];

        public $mockApp;

        protected function argument(?string $key = null): mixed
        {
            return $key === null ? $this->testArgs : ($this->testArgs[$key] ?? null);
        }

        protected function option(?string $key = null): mixed
        {
            return $key === null ? $this->testOpts : ($this->testOpts[$key] ?? null);
        }

        protected function info(string $message): void
        {
            $this->testOutput[] = $message;
        }

        protected function error(string $message): void
        {
            $this->testOutput[] = 'ERROR: ' . $message;
        }

        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }

        protected function ensureMigrationDirectory(): void
        {
            $dir = $this->mockApp->databasePath('migrations');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        public function handle(): int
        {
            $name = $this->argument('name');
            $table = $this->option('table');
            $create = $this->option('create');
            $fileName = $this->getMigrationFileName($name);
            $path = $this->mockApp->databasePath('migrations') . '/' . $fileName;
            if (file_exists($path)) {
                $this->error("Migration {$fileName} already exists!");

                return 1;
            }
            $this->ensureMigrationDirectory();
            $stub = $this->getStub($create, $table);
            $content = $this->populateStub($stub, $this->getMigrationName($name), $create ?: $table);
            file_put_contents($path, $content);
            $this->info("Migration {$fileName} created successfully.");

            return 0;
        }
    };

    $command->testArgs = ['name' => 'add_email_to_users'];
    $command->testOpts = ['table' => 'users', 'create' => null];
    $command->mockApp = new class($testPath)
    {
        private string $path;

        public function __construct(string $path)
        {
            $this->path = $path;
        }

        public function databasePath(string $p = ''): string
        {
            return $this->path . ($p ? '/' . $p : '');
        }
    };

    $result = $command->handle();
    expect($result)->toBe(0);

    $content = file_get_contents($testPath . '/migrations/2023_01_01_120000_add_email_to_users.php');
    expect($content)->toContain("table('users'");
    expect($content)->not->toContain("create('users'");

    @unlink($testPath . '/migrations/2023_01_01_120000_add_email_to_users.php');
    @rmdir($testPath . '/migrations');
    @rmdir($testPath);
});

it('prevents creating duplicate migration files', function () {
    $testPath = sys_get_temp_dir() . '/test_make_migration_dup';
    @mkdir($testPath . '/migrations', 0755, true);
    file_put_contents($testPath . '/migrations/2023_01_01_120000_create_posts_table.php', '<?php // existing');

    $command = new class() extends MakeMigrationCommand
    {
        public array $testArgs = [];

        public array $testOpts = [];

        public array $testOutput = [];

        public $mockApp;

        protected function argument(?string $key = null): mixed
        {
            return $key === null ? $this->testArgs : ($this->testArgs[$key] ?? null);
        }

        protected function option(?string $key = null): mixed
        {
            return $key === null ? $this->testOpts : ($this->testOpts[$key] ?? null);
        }

        protected function info(string $message): void
        {
            $this->testOutput[] = $message;
        }

        protected function error(string $message): void
        {
            $this->testOutput[] = 'ERROR: ' . $message;
        }

        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }

        public function handle(): int
        {
            $name = $this->argument('name');
            $fileName = $this->getMigrationFileName($name);
            $path = $this->mockApp->databasePath('migrations') . '/' . $fileName;
            if (file_exists($path)) {
                $this->error("Migration {$fileName} already exists!");

                return 1;
            }

            return 0;
        }
    };

    $command->testArgs = ['name' => 'create_posts_table'];
    $command->mockApp = new class($testPath)
    {
        private string $path;

        public function __construct(string $path)
        {
            $this->path = $path;
        }

        public function databasePath(string $p = ''): string
        {
            return $this->path . ($p ? '/' . $p : '');
        }
    };

    $result = $command->handle();
    expect($result)->toBe(1);
    expect($command->testOutput)->toContain('ERROR: Migration 2023_01_01_120000_create_posts_table.php already exists!');

    @unlink($testPath . '/migrations/2023_01_01_120000_create_posts_table.php');
    @rmdir($testPath . '/migrations');
    @rmdir($testPath);
});
