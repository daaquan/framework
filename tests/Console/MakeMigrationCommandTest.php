<?php

use Phare\Console\Commands\MakeMigrationCommand;
use Tests\TestCase;

class MakeMigrationCommandTest extends TestCase
{
    protected string $testMigrationPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary directory for test migrations
        $this->testMigrationPath = sys_get_temp_dir() . '/test_make_migration';
        if (!is_dir($this->testMigrationPath)) {
            mkdir($this->testMigrationPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test migrations directory
        if (is_dir($this->testMigrationPath)) {
            $files = glob($this->testMigrationPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testMigrationPath);
        }
        
        parent::tearDown();
    }
}

it('can create basic migration', function () {
    $command = new class extends MakeMigrationCommand {
        protected array $arguments = [];
        protected array $options = [];
        protected array $output = [];
        
        public function argument(string $key): mixed
        {
            return $this->arguments[$key] ?? null;
        }
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setArgument(string $key, mixed $value): void
        {
            $this->arguments[$key] = $value;
        }
        
        public function info(string $message): void
        {
            $this->output[] = $message;
        }
        
        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }
        
        protected function ensureMigrationDirectory(): void
        {
            // Use test directory
        }
        
        public function getOutput(): array
        {
            return $this->output;
        }
    };
    
    $command->setApplication($this->app);
    $command->setArgument('name', 'create_posts_table');
    
    // Override the application's databasePath method for testing
    $testApp = new class($this->app) {
        private $app;
        
        public function __construct($app)
        {
            $this->app = $app;
        }
        
        public function databasePath(string $path = ''): string
        {
            return sys_get_temp_dir() . '/test_make_migration' . ($path ? '/' . $path : '');
        }
        
        public function __call($name, $arguments)
        {
            return $this->app->$name(...$arguments);
        }
    };
    
    $command->setApplication($testApp);
    $result = $command->handle();
    
    expect($result)->toBe(0);
    expect($command->getOutput())->toContain('Migration 2023_01_01_120000_create_posts_table.php created successfully.');
    
    // Check if file was created
    $expectedFile = $this->testMigrationPath . '/migrations/2023_01_01_120000_create_posts_table.php';
    expect(file_exists($expectedFile))->toBe(true);
    
    $content = file_get_contents($expectedFile);
    expect($content)->toContain('class extends Migration');
    expect($content)->toContain('public function up()');
    expect($content)->toContain('public function down()');
})->uses(MakeMigrationCommandTest::class);

it('can create migration with create table option', function () {
    $command = new class extends MakeMigrationCommand {
        protected array $arguments = [];
        protected array $options = [];
        protected array $output = [];
        
        public function argument(string $key): mixed
        {
            return $this->arguments[$key] ?? null;
        }
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setArgument(string $key, mixed $value): void
        {
            $this->arguments[$key] = $value;
        }
        
        public function setOption(string $key, mixed $value): void
        {
            $this->options[$key] = $value;
        }
        
        public function info(string $message): void
        {
            $this->output[] = $message;
        }
        
        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }
        
        protected function ensureMigrationDirectory(): void
        {
            if (!is_dir($this->testMigrationPath . '/migrations')) {
                mkdir($this->testMigrationPath . '/migrations', 0755, true);
            }
        }
        
        public function getOutput(): array
        {
            return $this->output;
        }
        
        private string $testMigrationPath;
        
        public function setTestPath(string $path): void
        {
            $this->testMigrationPath = $path;
        }
    };
    
    $command->setTestPath($this->testMigrationPath);
    
    $testApp = new class($this->app, $this->testMigrationPath) {
        private $app;
        private string $testPath;
        
        public function __construct($app, string $testPath)
        {
            $this->app = $app;
            $this->testPath = $testPath;
        }
        
        public function databasePath(string $path = ''): string
        {
            return $this->testPath . ($path ? '/' . $path : '');
        }
        
        public function __call($name, $arguments)
        {
            return $this->app->$name(...$arguments);
        }
    };
    
    $command->setApplication($testApp);
    $command->setArgument('name', 'create_users_table');
    $command->setOption('create', 'users');
    
    $result = $command->handle();
    
    expect($result)->toBe(0);
    
    // Check if file was created
    $expectedFile = $this->testMigrationPath . '/migrations/2023_01_01_120000_create_users_table.php';
    expect(file_exists($expectedFile))->toBe(true);
    
    $content = file_get_contents($expectedFile);
    expect($content)->toContain("create('users'");
    expect($content)->toContain('$table->id()');
    expect($content)->toContain('$table->timestamps()');
    expect($content)->toContain("dropIfExists('users')");
})->uses(MakeMigrationCommandTest::class);

it('can create migration with table modification option', function () {
    $command = new class extends MakeMigrationCommand {
        protected array $arguments = [];
        protected array $options = [];
        protected array $output = [];
        
        public function argument(string $key): mixed
        {
            return $this->arguments[$key] ?? null;
        }
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setArgument(string $key, mixed $value): void
        {
            $this->arguments[$key] = $value;
        }
        
        public function setOption(string $key, mixed $value): void
        {
            $this->options[$key] = $value;
        }
        
        public function info(string $message): void
        {
            $this->output[] = $message;
        }
        
        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }
        
        protected function ensureMigrationDirectory(): void
        {
            if (!is_dir($this->testMigrationPath . '/migrations')) {
                mkdir($this->testMigrationPath . '/migrations', 0755, true);
            }
        }
        
        private string $testMigrationPath;
        
        public function setTestPath(string $path): void
        {
            $this->testMigrationPath = $path;
        }
    };
    
    $command->setTestPath($this->testMigrationPath);
    
    $testApp = new class($this->app, $this->testMigrationPath) {
        private $app;
        private string $testPath;
        
        public function __construct($app, string $testPath)
        {
            $this->app = $app;
            $this->testPath = $testPath;
        }
        
        public function databasePath(string $path = ''): string
        {
            return $this->testPath . ($path ? '/' . $path : '');
        }
        
        public function __call($name, $arguments)
        {
            return $this->app->$name(...$arguments);
        }
    };
    
    $command->setApplication($testApp);
    $command->setArgument('name', 'add_email_to_users');
    $command->setOption('table', 'users');
    
    $result = $command->handle();
    
    expect($result)->toBe(0);
    
    // Check if file was created
    $expectedFile = $this->testMigrationPath . '/migrations/2023_01_01_120000_add_email_to_users.php';
    expect(file_exists($expectedFile))->toBe(true);
    
    $content = file_get_contents($expectedFile);
    expect($content)->toContain("table('users'");
    expect($content)->not->toContain("create('users'");
    expect($content)->not->toContain("dropIfExists('users')");
})->uses(MakeMigrationCommandTest::class);

it('prevents creating duplicate migration files', function () {
    // Create a file first
    if (!is_dir($this->testMigrationPath . '/migrations')) {
        mkdir($this->testMigrationPath . '/migrations', 0755, true);
    }
    
    $existingFile = $this->testMigrationPath . '/migrations/2023_01_01_120000_create_posts_table.php';
    file_put_contents($existingFile, '<?php // existing file');
    
    $command = new class extends MakeMigrationCommand {
        protected array $arguments = [];
        protected array $options = [];
        protected array $output = [];
        
        public function argument(string $key): mixed
        {
            return $this->arguments[$key] ?? null;
        }
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setArgument(string $key, mixed $value): void
        {
            $this->arguments[$key] = $value;
        }
        
        public function error(string $message): void
        {
            $this->output[] = 'ERROR: ' . $message;
        }
        
        public function info(string $message): void
        {
            $this->output[] = $message;
        }
        
        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }
        
        protected function ensureMigrationDirectory(): void
        {
            // Directory already exists
        }
        
        public function getOutput(): array
        {
            return $this->output;
        }
        
        private string $testMigrationPath;
        
        public function setTestPath(string $path): void
        {
            $this->testMigrationPath = $path;
        }
    };
    
    $command->setTestPath($this->testMigrationPath);
    
    $testApp = new class($this->app, $this->testMigrationPath) {
        private $app;
        private string $testPath;
        
        public function __construct($app, string $testPath)
        {
            $this->app = $app;
            $this->testPath = $testPath;
        }
        
        public function databasePath(string $path = ''): string
        {
            return $this->testPath . ($path ? '/' . $path : '');
        }
        
        public function __call($name, $arguments)
        {
            return $this->app->$name(...$arguments);
        }
    };
    
    $command->setApplication($testApp);
    $command->setArgument('name', 'create_posts_table');
    
    $result = $command->handle();
    
    expect($result)->toBe(1);
    expect($command->getOutput())->toContain('ERROR: Migration 2023_01_01_120000_create_posts_table.php already exists!');
})->uses(MakeMigrationCommandTest::class);

it('creates migration directory if it does not exist', function () {
    $command = new class extends MakeMigrationCommand {
        protected array $arguments = [];
        protected array $options = [];
        protected bool $directoryCreated = false;
        
        public function argument(string $key): mixed
        {
            return $this->arguments[$key] ?? null;
        }
        
        public function option(string $key): mixed
        {
            return $this->options[$key] ?? null;
        }
        
        public function setArgument(string $key, mixed $value): void
        {
            $this->arguments[$key] = $value;
        }
        
        public function info(string $message): void
        {
            // Mock output
        }
        
        protected function getMigrationFileName(string $name): string
        {
            return '2023_01_01_120000_' . $name . '.php';
        }
        
        protected function ensureMigrationDirectory(): void
        {
            $dir = $this->getApplication()->databasePath('migrations');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $this->directoryCreated = true;
            }
        }
        
        public function wasDirectoryCreated(): bool
        {
            return $this->directoryCreated;
        }
    };
    
    $testApp = new class($this->app, $this->testMigrationPath) {
        private $app;
        private string $testPath;
        
        public function __construct($app, string $testPath)
        {
            $this->app = $app;
            $this->testPath = $testPath;
        }
        
        public function databasePath(string $path = ''): string
        {
            return $this->testPath . '/new_migrations' . ($path ? '/' . $path : '');
        }
        
        public function __call($name, $arguments)
        {
            return $this->app->$name(...$arguments);
        }
    };
    
    $command->setApplication($testApp);
    $command->setArgument('name', 'create_test_table');
    
    $result = $command->handle();
    
    expect($result)->toBe(0);
    expect($command->wasDirectoryCreated())->toBe(true);
    expect(is_dir($this->testMigrationPath . '/new_migrations/migrations'))->toBe(true);
})->uses(MakeMigrationCommandTest::class);