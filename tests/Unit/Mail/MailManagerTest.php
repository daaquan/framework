<?php

use Phare\Container\Container;
use Phare\Mail\MailManager;

beforeEach(function () {
    $this->app = new Container();
    $this->app->singleton('config', fn () => [
        'mail' => [
            'default' => 'primary',
            'mailers' => [
                'primary' => ['driver' => 'smtp', 'host' => 'primary.host'],
                'log' => ['driver' => 'log', 'host' => 'logger'],
            ],
        ],
    ]);
});

it('exposes driver() as a Laravel-parity alias for mailer()', function () {
    $manager = new MailManager($this->app);

    expect($manager->driver('log'))->toBe($manager->mailer('log'));
    expect($manager->driver())->toBe($manager->mailer());
});

it('caches mailer instances by name', function () {
    $manager = new MailManager($this->app);

    expect($manager->mailer('log'))->toBe($manager->mailer('log'));
});
