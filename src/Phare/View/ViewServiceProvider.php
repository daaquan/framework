<?php

namespace Phare\View;

use Phalcon\Flash\Session as FlashSession;
use Phalcon\Html\Escaper;
use Phare\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // View-facing services that layouts/partials depend on ($flash, escaping).
        $this->app->singleton('escaper', Escaper::class);
        $this->app->singleton('flashSession', FlashSession::class);

        // Vite asset resolver, backing the @vite / @viteReactRefresh directives.
        $this->app->singleton('vite', fn () => new Vite());
        $this->app->bind(Vite::class, fn ($app) => $app['vite']);

        $this->app->singleton('view', function ($app) {
            $views = $app->bound('__views_path')
                ? $app->make('__views_path')
                : $app->resourcePath('views');
            $storage = $app->bound('__storage_path')
                ? $app->make('__storage_path')
                : $app->storagePath('framework/views');

            $blade = new Blade($views, $storage, Blade::MODE_AUTO);

            // Vite asset directives (resolve the bound Vite instance at runtime).
            $blade->directive('vite', fn ($expression) => "<?php echo app('vite')({$expression}); ?>");
            $blade->directive('viteReactRefresh', fn () => "<?php echo app('vite')->reactRefresh(); ?>");

            // Pagination labels are optional and depend on the translator,
            // which may not be bound yet depending on provider order.
            if ($app->bound('translate')) {
                $blade->setTranslationControl([
                    'pagination' => [
                        'first' => __('pagination.first'),
                        'next' => __('pagination.next'),
                        'prev' => __('pagination.previous'),
                        'last' => __('pagination.last'),
                    ],
                ]);
            }

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

        // Render returned View objects into the HTTP response
        $this->registerViewRenderBridge();
    }

    /**
     * Bridge controller-returned View objects to HTTP output.
     *
     * Controllers return `view('name')->with(...)` (a Phare\View\View). With
     * implicit-view rendering disabled, Phalcon only emits a returned value
     * when it is a ResponseInterface or a string. This listener renders any
     * returned View to a string so its markup reaches the response body.
     */
    protected function registerViewRenderBridge(): void
    {
        if (!isset($this->app['eventsManager']) || !isset($this->app['dispatcher'])) {
            return;
        }

        $app = $this->app;
        $eventsManager = $app['eventsManager'];

        $eventsManager->attach('dispatch:afterExecuteRoute', function () use ($app) {
            $dispatcher = $app['dispatcher'];
            $returned = $dispatcher->getReturnedValue();

            if ($returned instanceof View) {
                $dispatcher->setReturnedValue($returned->render());
            }
        });

        $app['dispatcher']->setEventsManager($eventsManager);
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

        // Flash messages are consumed by layouts/partials as $flash. Resolving
        // the flash service pulls in the session manager, which may be absent
        // in lightweight contexts (bare containers, console); degrade quietly.
        if ($this->app->bound('flashSession')) {
            try {
                $this->app['view']->share('flash', $this->app['flashSession']);
            } catch (\Throwable $e) {
                // Session/flash unavailable — skip sharing rather than fail boot.
            }
        }
    }
}
