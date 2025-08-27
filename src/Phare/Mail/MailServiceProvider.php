<?php

namespace Phare\Mail;

use Phare\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('mailer', function ($app) {
            $config = $app['config']['mail'] ?? [];
            return new Mailer($config);
        });

        $this->app->bind(Mailer::class, function ($app) {
            return $app['mailer'];
        });
    }

    public function boot(): void
    {
        // Register mail helper functions
        if (!function_exists('mail')) {
            function mail(): Mailer {
                return app('mailer');
            }
        }
    }
}