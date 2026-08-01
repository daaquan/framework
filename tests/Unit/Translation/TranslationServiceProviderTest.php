<?php

use Phalcon\Di\Di;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Application as ApplicationContract;
use Phare\Translation\TranslationServiceProvider;
use Phare\Translation\Translator;

beforeEach(function () {
    $this->app = new Container();

    // Set up basic config
    $this->app->singleton('config', fn () => [
        'app.locale' => 'en',
        'app.fallback_locale' => 'en',
    ]);

    // Mock resourcePath method
    $this->app->bind('path.resources', fn () => __DIR__ . '/../../Mock/resources');

    $di = new Di();
    $di->setShared(ApplicationContract::class, $this->app);
    Di::setDefault($di);

    $this->provider = new TranslationServiceProvider($this->app);
});

afterEach(function () {
    Di::reset();
});

test('registers translator service', function () {
    $this->provider->register();

    expect($this->app->has('translator'))->toBeTrue();
    expect($this->app->make('translator'))->toBeInstanceOf(Translator::class);
});

test('binds Translator class', function () {
    $this->provider->register();

    expect($this->app->make(Translator::class))->toBeInstanceOf(Translator::class);
    expect($this->app->make(Translator::class))->toBe($this->app->make('translator'));
});

test('configures translator with app config', function () {
    // Set test config
    $this->app->bind('config', fn () => [
        'app.locale' => 'es',
        'app.fallback_locale' => 'en',
    ], true);

    $this->provider->register();
    $translator = $this->app->make('translator');

    expect($translator->getLocale())->toBe('es');
    expect($translator->getFallback())->toBe('en');
});

test('uses default locale when config not set', function () {
    $this->provider->register();
    $translator = $this->app->make('translator');

    expect($translator->getLocale())->toBe('en');
    expect($translator->getFallback())->toBe('en');
});

test('boots translation helper functions', function () {
    $this->provider->register();
    $this->provider->boot();

    expect(function_exists('Phare\\Translation\\trans'))->toBeTrue();
    expect(function_exists('Phare\\Translation\\trans_choice'))->toBeTrue();
    expect(function_exists('Phare\\Translation\\__'))->toBeTrue();
});

test('trans helper function works', function () {
    $this->provider->register();
    $this->provider->boot();

    // Create a test translation
    $translator = $this->app->make('translator');

    // Mock a simple translation by adding it directly
    $reflection = new ReflectionClass($translator);
    $loadedProperty = $reflection->getProperty('loaded');
    $loadedProperty->setAccessible(true);
    $loadedProperty->setValue($translator, [
        'en' => [
            'messages' => ['test' => 'Hello World'],
        ],
    ]);

    expect(\Phare\Translation\trans('messages.test'))->toBe('Hello World');
});

test('__ helper function works as alias', function () {
    $this->provider->register();
    $this->provider->boot();

    // Mock translation
    $translator = $this->app->make('translator');
    $reflection = new ReflectionClass($translator);
    $loadedProperty = $reflection->getProperty('loaded');
    $loadedProperty->setAccessible(true);
    $loadedProperty->setValue($translator, [
        'en' => [
            'messages' => ['test' => 'Hello World'],
        ],
    ]);

    expect(\Phare\Translation\__('messages.test'))->toBe('Hello World');
});

test('trans_choice helper function works', function () {
    $this->provider->register();
    $this->provider->boot();

    // Mock translation with pluralization
    $translator = $this->app->make('translator');
    $reflection = new ReflectionClass($translator);
    $loadedProperty = $reflection->getProperty('loaded');
    $loadedProperty->setAccessible(true);
    $loadedProperty->setValue($translator, [
        'en' => [
            'messages' => ['items' => 'no items|one item|:count items'],
        ],
    ]);

    expect(\Phare\Translation\trans_choice('messages.items', 1))->toBe('one item');
    expect(\Phare\Translation\trans_choice('messages.items', 5, ['count' => 5]))->toBe('5 items');
});

test('helper functions work with replacements', function () {
    $this->provider->register();
    $this->provider->boot();

    // Mock translation
    $translator = $this->app->make('translator');
    $reflection = new ReflectionClass($translator);
    $loadedProperty = $reflection->getProperty('loaded');
    $loadedProperty->setAccessible(true);
    $loadedProperty->setValue($translator, [
        'en' => [
            'messages' => ['greeting' => 'Hello :name'],
        ],
    ]);

    expect(\Phare\Translation\trans('messages.greeting', ['name' => 'John']))->toBe('Hello John');
    expect(\Phare\Translation\__('messages.greeting', ['name' => 'Jane']))->toBe('Hello Jane');
});

test('helper functions work with custom locale', function () {
    $this->provider->register();
    $this->provider->boot();

    // Mock translations
    $translator = $this->app->make('translator');
    $reflection = new ReflectionClass($translator);
    $loadedProperty = $reflection->getProperty('loaded');
    $loadedProperty->setAccessible(true);
    $loadedProperty->setValue($translator, [
        'en' => ['messages' => ['hello' => 'Hello']],
        'es' => ['messages' => ['hello' => 'Hola']],
    ]);

    expect(\Phare\Translation\trans('messages.hello', [], 'es'))->toBe('Hola');
    expect(\Phare\Translation\__('messages.hello', [], 'en'))->toBe('Hello');
});

test('does not redeclare functions if they already exist', function () {
    // First boot
    $this->provider->register();
    $this->provider->boot();

    // Second boot should not cause errors
    $secondProvider = new TranslationServiceProvider($this->app);
    $secondProvider->boot();

    expect(function_exists('Phare\\Translation\\trans'))->toBeTrue();
    expect(function_exists('Phare\\Translation\\trans_choice'))->toBeTrue();
    expect(function_exists('Phare\\Translation\\__'))->toBeTrue();
});
