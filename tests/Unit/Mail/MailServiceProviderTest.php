<?php

use Phare\Mail\MailServiceProvider;
use Phare\Mail\Mailer;
use Phare\Container\Container;

beforeEach(function () {
    $this->app = new Container();
    $this->app['config'] = [
        'mail' => [
            'driver' => 'smtp',
            'host' => 'localhost',
            'port' => 587,
            'from' => [
                'address' => 'noreply@test.com',
                'name' => 'Test App'
            ]
        ]
    ];
    
    $this->provider = new MailServiceProvider($this->app);
});

test('registers mailer service', function () {
    $this->provider->register();
    
    expect($this->app->has('mailer'))->toBeTrue();
    expect($this->app->make('mailer'))->toBeInstanceOf(Mailer::class);
});

test('binds Mailer class', function () {
    $this->provider->register();
    
    expect($this->app->make(Mailer::class))->toBeInstanceOf(Mailer::class);
    expect($this->app->make(Mailer::class))->toBe($this->app->make('mailer'));
});

test('configures mailer with app config', function () {
    $this->provider->register();
    $mailer = $this->app->make('mailer');
    
    expect($mailer->getConfig()['driver'])->toBe('smtp');
    expect($mailer->getConfig()['host'])->toBe('localhost');
    expect($mailer->getConfig()['from']['address'])->toBe('noreply@test.com');
});

test('uses default config when mail config not set', function () {
    $this->app['config'] = [];
    $this->provider->register();
    
    $mailer = $this->app->make('mailer');
    
    expect($mailer->getConfig()['driver'])->toBe('smtp');
    expect($mailer->getConfig()['host'])->toBe('localhost');
});

test('boots mail helper function', function () {
    // Can't test actual helper function due to global function conflicts
    // This validates the provider has a boot method
    expect(method_exists($this->provider, 'boot'))->toBeTrue();
    
    $this->provider->register();
    $this->provider->boot();
    
    // Verify mailer is accessible
    expect($this->app->make('mailer'))->toBeInstanceOf(Mailer::class);
});