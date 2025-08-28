<?php

namespace Phare\Foundation\Bootstrap;

use Phare\Config\EnvironmentDetector;
use Phare\Config\EnvironmentManager;
use Phare\Contracts\Foundation\Application;

class DetectEnvironment
{
    public function bootstrap(Application $app): void
    {
        $environments = $app->make('config')->get('environments.environments', []);
        
        $detector = new EnvironmentDetector($environments);
        $manager = new EnvironmentManager($detector);
        
        // Set custom environment files if configured
        $files = $app->make('config')->get('environments.files', []);
        if (!empty($files)) {
            $manager->setEnvironmentFiles($files);
        }

        $environment = $manager->detect($app->basePath(), $app->environmentPath());
        
        $app->detectEnvironment(function () use ($environment) {
            return $environment;
        });

        // Apply environment-specific overrides
        $this->applyEnvironmentOverrides($app, $environment);

        // Register the environment manager
        $app->instance('env.manager', $manager);
        $app->instance('env.detector', $detector);
    }

    protected function applyEnvironmentOverrides(Application $app, string $environment): void
    {
        $overrides = $app->make('config')->get("environments.overrides.{$environment}", []);
        
        foreach ($overrides as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}