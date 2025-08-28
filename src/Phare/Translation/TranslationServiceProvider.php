<?php

namespace Phare\Translation;

use Phare\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('translator', function ($app) {
            $config = $app['config'] ?? [];
            
            $translator = new Translator(
                $config['app.locale'] ?? 'en',
                $config['app.fallback_locale'] ?? 'en'
            );

            // Add translation path if available
            if (isset($app['path.resources'])) {
                $translator->addPath($app['path.resources'] . '/lang');
            } elseif (method_exists($app, 'resourcePath')) {
                $translator->addPath($app->resourcePath('lang'));
            }

            return $translator;
        });

        $this->app->bind(Translator::class, function ($app) {
            return $app['translator'];
        });
    }

    public function boot(): void
    {
        // Register translation helper functions
        if (!function_exists('trans')) {
            function trans(string $key, array $replace = [], ?string $locale = null): string {
                return app('translator')->trans($key, $replace, $locale);
            }
        }

        if (!function_exists('trans_choice')) {
            function trans_choice(string $key, int $number, array $replace = [], ?string $locale = null): string {
                return app('translator')->transChoice($key, $number, $replace, $locale);
            }
        }

        if (!function_exists('__')) {
            function __(string $key, array $replace = [], ?string $locale = null): string {
                return app('translator')->trans($key, $replace, $locale);
            }
        }
    }
}