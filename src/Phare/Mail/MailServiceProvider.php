<?php

namespace Phare\Mail;

use Phare\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('mailer', function ($app) {
            $configService = $app->bound('config') ? $app->make('config') : [];
            $config = [];

            if (is_array($configService)) {
                $config = $configService['mail'] ?? [];
            } elseif ($configService instanceof \Phalcon\Config\Config) {
                $config = $configService->path('mail', []);
            } elseif (is_object($configService) && method_exists($configService, 'get')) {
                $config = $configService->get('mail', []);
            }

            return new Mailer($config);
        });

        $this->app->bind(Mailer::class, function ($app) {
            return $app->make('mailer');
        });
    }

    public function boot(): void
    {
        // Register mail helper functions
        if (!function_exists('mail')) {
            function mail(): Mailer
            {
                return app('mailer');
            }
        }
    }
}
