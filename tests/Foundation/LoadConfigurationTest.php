<?php

use Phalcon\Mvc\Micro;
use Phare\Foundation\AbstractApplication;
use Phare\Foundation\Bootstrap\LoadConfiguration;

class LoadConfigurationTestApplication extends AbstractApplication
{
    protected function createApplication()
    {
        return (new Micro())->notFound(static fn () => 'Not found');
    }

    public function handle($uri)
    {
        return $this->app->handle($uri);
    }

    public function terminate()
    {
        $this->app->stop();
    }
}

function createConfigFixture(string $basePath, string $appName = 'Phare Test'): void
{
    if (!is_dir($basePath . '/config')) {
        mkdir($basePath . '/config', 0777, true);
    }
    if (!is_dir($basePath . '/bootstrap/cache')) {
        mkdir($basePath . '/bootstrap/cache', 0777, true);
    }

    file_put_contents($basePath . '/.env', "APP_ENV=testing\n");
    file_put_contents(
        $basePath . '/config/app.php',
        "<?php\n\nreturn [\n    'name' => '{$appName}',\n];\n"
    );
}

function deleteDirectoryRecursively(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $item->isDir() ? rmdir($path) : unlink($path);
    }

    rmdir($dir);
}

beforeEach(function () {
    $this->basePath = sys_get_temp_dir() . '/phare-config-test-' . bin2hex(random_bytes(6));
    createConfigFixture($this->basePath);
    putenv('APP_ENV=testing');
});

afterEach(function () {
    deleteDirectoryRecursively($this->basePath);
});

it('generates and loads configuration when cache is missing', function () {
    $app = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper = new LoadConfiguration();

    $bootstrapper->register($app);

    $cachedPath = $app->getCachedConfigPath();
    expect(file_exists($cachedPath))->toBeTrue();
    expect($app->make('config')->path('app.name'))->toBe('Phare Test');
});

it('regenerates cache in testing even when configuration has not changed', function () {
    // Ensure cached file mtime can be compared across runs.
    sleep(1);

    $app = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper = new LoadConfiguration();
    $bootstrapper->register($app);

    $cachedPath = $app->getCachedConfigPath();
    $mtimeBefore = filemtime($cachedPath);

    sleep(1);

    $freshApp = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper->register($freshApp);
    $mtimeAfter = filemtime($cachedPath);

    expect($mtimeAfter)->toBeGreaterThan($mtimeBefore);
});

it('regenerates cache in testing when configuration changes', function () {
    $app = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper = new LoadConfiguration();
    $bootstrapper->register($app);

    $cachedPath = $app->getCachedConfigPath();
    $mtimeBefore = filemtime($cachedPath);

    sleep(1);
    createConfigFixture($this->basePath, 'Updated Name');

    $freshApp = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper->register($freshApp);
    $mtimeAfter = filemtime($cachedPath);

    expect($mtimeAfter)->toBeGreaterThan($mtimeBefore);
    expect($freshApp->make('config')->path('app.name'))->toBe('Updated Name');
});
