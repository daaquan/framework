<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;

class MakeSeederCommand extends Command
{
    protected string $signature = 'make:seeder {name : The seeder name}';

    protected string $description = 'Create a new seeder class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $className = $this->getClassName($name);
        $fileName = "{$className}.php";

        $directory = $this->getApplication()->databasePath('seeders');
        $path = $directory . '/' . $fileName;

        if (file_exists($path)) {
            $this->error("Seeder {$fileName} already exists!");

            return 1;
        }

        $this->ensureDirectory($directory);

        $content = $this->getStub($className);
        file_put_contents($path, $content);

        $this->info("Seeder {$fileName} created successfully.");

        return 0;
    }

    protected function getClassName(string $name): string
    {
        $name = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));

        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        return $name;
    }

    protected function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    protected function getStub(string $className): string
    {
        return <<<STUB
<?php

namespace Database\\Seeders;

use Phare\\Database\\Seeder;

class {$className} extends Seeder
{
    public function run(): void
    {
        //
    }
}
STUB;
    }
}
