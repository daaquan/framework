<?php

namespace Phare\Inertia;

use Phalcon\Http\ResponseInterface;
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
            // Resolve via make() so we use the same view instance the @inertia
            // directive was registered on (array-access can yield a different one).
            $factory->setViewRenderer(function (string $view, array $data) use ($app) {
                return $app->make('view')->make($view, $data)->render();
            });

            return $factory;
        });

        $this->app->bind(ResponseFactory::class, fn ($app) => $app['inertia']);
    }

    public function boot(): void
    {
        $this->registerBladeDirectives();
        $this->registerResponseBridge();
        $this->registerSeeOtherRedirects();
    }

    /**
     * Inertia protocol: a redirect after PUT/PATCH/DELETE must be a 303. The
     * browser re-sends the original method on a 302 for anything but POST, so
     * PATCH /settings/profile -> 302 would PATCH the redirect target too.
     */
    protected function registerSeeOtherRedirects(): void
    {
        if (!isset($this->app['eventsManager'])) {
            return;
        }

        $app = $this->app;

        $app['eventsManager']->attach('application:beforeSendResponse', function ($event, $source, $response) use ($app) {
            $request = $app['request'];

            if ($response instanceof ResponseInterface
                && (int)$response->getStatusCode() === 302
                && $request->getHeader('X-Inertia')
                && in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'], true)
            ) {
                $response->setStatusCode(303);
            }
        });
    }

    /**
     * Bridge controller-returned Inertia Response objects to HTTP output.
     *
     * A `Phare\Inertia\Response` is neither a Phalcon ResponseInterface nor a
     * string, so the dispatcher would drop it. This listener resolves it to the
     * concrete HTTP response (JSON for Inertia visits, HTML otherwise) so its
     * body, status, and headers reach the client.
     */
    protected function registerResponseBridge(): void
    {
        if (!isset($this->app['eventsManager']) || !isset($this->app['dispatcher'])) {
            return;
        }

        $app = $this->app;
        $eventsManager = $app['eventsManager'];

        $eventsManager->attach('dispatch:afterExecuteRoute', function () use ($app) {
            $dispatcher = $app['dispatcher'];
            $returned = $dispatcher->getReturnedValue();

            if (!$returned instanceof Response) {
                return;
            }

            // Resolve to the concrete HTTP response, then copy its status,
            // headers, and body onto the shared application response, which is
            // what the kernel ultimately emits.
            $http = $returned->toResponse($app['request']);
            $response = $app['response'];
            $response->setStatusCode($http->getStatusCode() ?: 200);
            $response->setContent($http->getContent());

            foreach ($http->getHeaders()->toArray() as $name => $value) {
                if (is_string($name) && $value !== null) {
                    $response->setHeader($name, $value);
                }
            }

            $dispatcher->setReturnedValue($http->getContent());
        });

        $app['dispatcher']->setEventsManager($eventsManager);
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
