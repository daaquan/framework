<?php

use Phare\Container\Container;
use Phare\Mail\Mailer;
use Phare\Mail\MailManager;
use Phare\Mail\MailServiceProvider;

beforeEach(function () {
    $this->app = new Container();
    $this->app->singleton('config', fn () => [
        'mail' => [
            'driver' => 'smtp',
            'host' => 'localhost',
            'port' => 587,
            'from' => [
                'address' => 'noreply@test.com',
                'name' => 'Test App',
            ],
        ],
    ]);

    $this->provider = new MailServiceProvider();
});

test('registers mailer and mail.manager services', function () {
    $this->provider->register($this->app);

    expect($this->app->has('mailer'))->toBeTrue();
    expect($this->app->has('mail.manager'))->toBeTrue();
    expect($this->app->make('mailer'))->toBeInstanceOf(Mailer::class);
    expect($this->app->make('mail.manager'))->toBeInstanceOf(MailManager::class);
});

test('binds Mailer class', function () {
    $this->provider->register($this->app);

    expect($this->app->make(Mailer::class))->toBeInstanceOf(Mailer::class);
    expect($this->app->make(Mailer::class))->toBe($this->app->make('mailer'));
});

test('configures default mailer with top-level mail config', function () {
    $this->provider->register($this->app);
    $mailer = $this->app->make('mailer');

    expect($mailer->getConfig()['driver'])->toBe('smtp');
    expect($mailer->getConfig()['host'])->toBe('localhost');
    expect($mailer->getConfig()['from']['address'])->toBe('noreply@test.com');
});

test('falls back to Mailer defaults when mail config not set', function () {
    $this->app->bind('config', fn () => [], true);
    $this->provider->register($this->app);

    $mailer = $this->app->make('mailer');

    expect($mailer->getConfig()['driver'])->toBe('smtp');
    expect($mailer->getConfig()['host'])->toBe('localhost');
});

test('mail.manager resolves named mailer from mail.mailers config', function () {
    $this->app->bind('config', fn () => [
        'mail' => [
            'default' => 'primary',
            'mailers' => [
                'primary' => ['driver' => 'smtp', 'host' => 'primary.host'],
                'log' => ['driver' => 'log', 'host' => 'logger'],
            ],
        ],
    ], true);

    $this->provider->register($this->app);

    $log = $this->app->make('mail.manager')->mailer('log');

    expect($log)->toBeInstanceOf(Mailer::class);
    expect($log->getConfig()['driver'])->toBe('log');
    expect($log->getConfig()['host'])->toBe('logger');
});
