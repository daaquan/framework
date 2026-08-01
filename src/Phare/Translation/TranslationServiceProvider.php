<?php

namespace Phare\Translation;

use Phalcon\Config\Config;
use Phare\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('translator', function ($app) {
            $configService = $app->bound('config') ? $app->make('config') : [];
            $config = [];

            if (is_array($configService)) {
                $config = $configService;
            } elseif ($configService instanceof Config) {
                $config = $configService->toArray();
            } elseif (is_object($configService) && method_exists($configService, 'all')) {
                $config = $configService->all();
            }

            $translator = new Translator(
                $config['app.locale'] ?? 'en',
                $config['app.fallback_locale'] ?? 'en'
            );

            // Add translation path if available
            if ($app->bound('path.resources')) {
                $translator->addPath($app->make('path.resources') . '/lang');
            } elseif (method_exists($app, 'resourcePath')) {
                $translator->addPath($app->resourcePath('lang'));
            }

            return $translator;
        });

        $this->app->bind(Translator::class, function ($app) {
            return $app->make('translator');
        });
    }

    public function boot(): void
    {
        // Register translation helper functions
        if (!function_exists(__NAMESPACE__ . '\\trans')) {
            function trans(string $key, array $replace = [], ?string $locale = null): string
            {
                $translator = app('translator') ?? container('translator');

                return $translator->trans($key, $replace, $locale);
            }
        }

        if (!function_exists(__NAMESPACE__ . '\\trans_choice')) {
            function trans_choice(string $key, int $number, array $replace = [], ?string $locale = null): string
            {
                $translator = app('translator') ?? container('translator');

                return $translator->transChoice($key, $number, $replace, $locale);
            }
        }

        if (!function_exists(__NAMESPACE__ . '\\__')) {
            function __(string $key, array $replace = [], ?string $locale = null): string
            {
                $translator = app('translator') ?? container('translator');

                return $translator->trans($key, $replace, $locale);
            }
        }
    }
}
