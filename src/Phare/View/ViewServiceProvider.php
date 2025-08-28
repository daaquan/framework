<?php

namespace Phare\View;

use Phare\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('view', function ($app) {
            $factory = new Factory($app);

            // Register common view extensions
            $factory->addExtension('.blade.php', 'blade');
            $factory->addExtension('.php', 'php');

            return $factory;
        });

        $this->app->bind(Factory::class, function ($app) {
            return $app['view'];
        });

        // Register view helper function
        if (!function_exists('view')) {
            function view(?string $view = null, array $data = [], array $mergeData = [])
            {
                $factory = app('view');

                if (func_num_args() === 0) {
                    return $factory;
                }

                return $factory->make($view, $data, $mergeData);
            }
        }
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
