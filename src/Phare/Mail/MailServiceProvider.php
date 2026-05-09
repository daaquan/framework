<?php

namespace Phare\Mail;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phare\Foundation\AbstractApplication as Application;

class MailServiceProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
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
