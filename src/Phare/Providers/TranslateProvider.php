<?php

namespace Phare\Providers;

use Phalcon\Translate\InterpolatorFactory;
use Phalcon\Translate\TranslateFactory;
use Phare\Foundation\AbstractApplication as Application;
use Phare\Support\ServiceProvider;

class TranslateProvider extends ServiceProvider
{
    /**
     * Registers a service provider.
     */
    public function register(): void
    {
        /** @var Application $app */
        $app = $this->app;
        $app->singleton('translate', function () use ($app) {
            $path = $app->languagePath($app['config']->path('app.locale', 'en'));

            $content = [];
            foreach (glob("$path/*.php") as $file) {
                $domain = pathinfo($file)['filename'];

                $phrases = new \RecursiveIteratorIterator(new \RecursiveArrayIterator(require $file));
                foreach ($phrases as $leafValue) {
                    $keys = [];
                    foreach (range(0, $phrases->getDepth()) as $depth) {
                        $keys[] = $phrases->getSubIterator($depth)?->key();
                    }
                    $content["$domain." . implode('.', $keys)] = $leafValue;
                }
            }

            $factory = new TranslateFactory(new InterpolatorFactory());
            $options = [
                'defaultInterpolator' => 'associativeArray',
                'content' => $content,
                'triggerError' => true,
            ];

            return $factory->newInstance('array', $options);
        });
    }
}
