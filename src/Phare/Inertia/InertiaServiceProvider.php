<?php

namespace Phare\Inertia;

use Phare\Support\ServiceProvider;
use Phare\View\Engines\BladeEngine;

class InertiaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('inertia', function ($app) {
            $factory = new ResponseFactory();

            // HTML (non-Inertia) visits render the root view through the view
            // factory, so the @inertia directive can emit the data-page element.
            $factory->setViewRenderer(function (string $view, array $data) use ($app) {
                return $app['view']->make($view, $data)->render();
            });

            return $factory;
        });

        $this->app->bind(ResponseFactory::class, fn ($app) => $app['inertia']);
    }

    public function boot(): void
    {
        $this->registerBladeDirectives();
    }

    /**
     * Register @inertia (root element) and @inertiaHead (SSR head placeholder).
     */
    protected function registerBladeDirectives(): void
    {
        if (!$this->app->bound('view')) {
            return;
        }

        $engine = $this->app->make('view')->getEngine();
        if (!$engine instanceof BladeEngine) {
            return;
        }

        $blade = $engine->getBlade();

        $blade->directive('inertia', function () {
            return '<?php echo \Phare\Inertia\ResponseFactory::renderRootElement($page ?? ["component" => "", "props" => [], "url" => "/", "version" => null]); ?>';
        });

        // SSR is not implemented yet; emit nothing so layouts can include it safely.
        $blade->directive('inertiaHead', fn () => '');
    }
}
