<?php

use Phare\Container\Container;
use Phare\Translation\TranslationServiceProvider;
use Phare\Translation\Translator;

beforeEach(function () {
    $this->app = new Container();

    // Set up basic config
    $this->app['config'] = [
        'app.locale' => 'en',
        'app.fallback_locale' => 'en',
    ];

    // Mock resourcePath method
    $this->app->bind('path.resources', function () {
        return __DIR__ . '/../../Mock/resources';
    });

    $this->provider = new TranslationServiceProvider($this->app);
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
    $this->app['config'] = [
        'app.locale' => 'es',
        'app.fallback_locale' => 'en',
    ];

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

    expect(function_exists('trans'))->toBeTrue();
    expect(function_exists('trans_choice'))->toBeTrue();
    expect(function_exists('__'))->toBeTrue();
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

    expect(trans('messages.test'))->toBe('Hello World');
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

    expect(__('messages.test'))->toBe('Hello World');
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

    expect(trans_choice('messages.items', 1))->toBe('one item');
    expect(trans_choice('messages.items', 5, ['count' => 5]))->toBe('5 items');
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

    expect(trans('messages.greeting', ['name' => 'John']))->toBe('Hello John');
    expect(__('messages.greeting', ['name' => 'Jane']))->toBe('Hello Jane');
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

    expect(trans('messages.hello', [], 'es'))->toBe('Hola');
    expect(__('messages.hello', [], 'en'))->toBe('Hello');
});

test('does not redeclare functions if they already exist', function () {
    // First boot
    $this->provider->register();
    $this->provider->boot();

    // Second boot should not cause errors
    $secondProvider = new TranslationServiceProvider($this->app);
    $secondProvider->boot();

    expect(function_exists('trans'))->toBeTrue();
    expect(function_exists('trans_choice'))->toBeTrue();
    expect(function_exists('__'))->toBeTrue();
});
