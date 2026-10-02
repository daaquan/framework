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
    $app = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper = new LoadConfiguration();
    $bootstrapper->register($app);

    $cachedPath = $app->getCachedConfigPath();
    $cached = require $cachedPath;
    $cached['app']['name'] = 'Stale cached name';
    file_put_contents($cachedPath, '<?php return ' . var_export($cached, true) . ';');

    $freshApp = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper->register($freshApp);

    expect($freshApp->make('config')->path('app.name'))->toBe('Phare Test')
        ->and((require $cachedPath)['app']['name'])->toBe('Phare Test');
});

it('regenerates cache in testing when configuration changes', function () {
    $app = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper = new LoadConfiguration();
    $bootstrapper->register($app);

    $cachedPath = $app->getCachedConfigPath();
    createConfigFixture($this->basePath, 'Updated Name');

    $freshApp = new LoadConfigurationTestApplication($this->basePath);
    $bootstrapper->register($freshApp);

    expect($freshApp->make('config')->path('app.name'))->toBe('Updated Name')
        ->and((require $cachedPath)['app']['name'])->toBe('Updated Name');
});
