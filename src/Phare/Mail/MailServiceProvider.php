<?php

namespace Phare\Mail;

use Phare\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;
        $app->singleton('mail.manager', function ($app) {
            return new MailManager($app);
        });

        // Backwards compat: callers continue to do `app('mailer')->send(...)`.
        // Returns the default Mailer via MailManager so single- and multi-mailer
        // configs both work.
        $app->singleton('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        $app->bind(Mailer::class, function ($app) {
            return $app->make('mailer');
        });
    }
}
