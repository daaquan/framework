<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Flash\Session as FlashSession;
use Phalcon\Html\Escaper;
use Phare\Foundation\AbstractApplication as Application;
use Phare\View\Blade;
use Phare\View\BladeOne;
use Phare\View\BladeView as View;

/**
 * @see https://daisyui.com/components/
 * @see https://tailwind-elements.com/quick-start/
 * @see https://heroicons.com/
 */
class BladeViewProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('blade', function () use ($app) {
            $blade = new Blade(
                $app->resourcePath('views'),
                $app->storagePath('framework/views'),
                BladeOne::MODE_AUTO
            );
            $blade->useDaisyui();
            $blade->setTranslationControl([
                'pagination' => [
                    'first' => __('pagination.first'),
                    'next' => __('pagination.next'),
                    'prev' => __('pagination.previous'),
                    'last' => __('pagination.last'),
                ],
            ]);

            return $blade;
        });

        $app->singleton('escaper', Escaper::class);

        $app->singleton('flashSession', FlashSession::class);

        $app->singleton('view', function () use ($app) {
            $viewDir = $app->basePath('resources/views');

            return (new \Phare\View\BladeView())
                ->enable()
                ->registerEngines(['.blade.php' => 'blade'])
                ->setLayoutsDir($viewDir . 'layouts/')
                ->setPartialsDir($viewDir . 'partials/')
                ->setRenderLevel(View::LEVEL_LAYOUT)
                ->setViewsDir($viewDir)
                ->setVar('flash', $app['flashSession']);
        });

        $eventsManager = $app['eventsManager'];
        $eventsManager->attach('dispatch:afterExecuteRoute', function () use ($app) {
            try {
                /** @var \Phalcon\Mvc\Dispatcher $dispatcher */
                $dispatcher = $app['dispatcher'];

                $view = $dispatcher->getParam('bladeView');
                if (empty($view)) {
                    // Get blade view from controller namespace.
                    $view = implode('.', [
                        $dispatcher->getNamespaceName(),
                        $dispatcher->getControllerName(),
                        $dispatcher->getActionName(),
                    ]);
                    $view = explode('\\', $view);
                    $view = end($view);
                    $view = strtolower($view);
                }

                $content = $app['blade']->run($view, $app['view']->getParamsToView());
                $dispatcher->setReturnedValue($content);

                // Clear the compiled views if we are in local environment
                if ($app->environment('local', 'testing')) {
                    ob_start();
                    $app['blade']->clearCompile();
                    ob_end_clean();
                }
            } catch (\Exception $e) {
                // Do nothing
            }
        });
        $app['dispatcher']->setEventsManager($eventsManager);
    }
}
