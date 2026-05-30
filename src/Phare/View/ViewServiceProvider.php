<?php

namespace Phare\View;

use Phare\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('view', function ($app) {
            $views = $app->bound('__views_path')
                ? $app->make('__views_path')
                : $app->resourcePath('views');
            $storage = $app->bound('__storage_path')
                ? $app->make('__storage_path')
                : $app->storagePath('framework/views');

            $blade = new Blade($views, $storage, Blade::MODE_DEBUG);
            $engine = new Engines\BladeEngine($blade);

            $factory = new Factory($app, $engine);

            // Register common view extensions
            $factory->addExtension('.blade.php', 'blade');
            $factory->addExtension('.php', 'php');

            return $factory;
        });

        $this->app->bind(Factory::class, function ($app) {
            return $app['view'];
        });
    }

    public function boot(): void
    {
        // Register default view composers if configured
        $this->registerComposers();

        // Share global view data
        $this->shareGlobalData();
    }

    /**
     * Register view composers.
     */
    protected function registerComposers(): void
    {
        $composers = $this->app['config']['view.composers'] ?? [];

        foreach ($composers as $view => $composer) {
            $this->app['view']->composer($view, $composer);
        }
    }

    /**
     * Share global view data.
     */
    protected function shareGlobalData(): void
    {
        $shared = $this->app['config']['view.shared'] ?? [];

        foreach ($shared as $key => $value) {
            $this->app['view']->share($key, $value);
        }

        // Share common application data
        $this->app['view']->share('app', $this->app);

        if (isset($this->app['config'])) {
            $this->app['view']->share('config', $this->app['config']);
        }
    }
}
