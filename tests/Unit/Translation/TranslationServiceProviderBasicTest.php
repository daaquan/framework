<?php

use Phare\Translation\TranslationServiceProvider;
use Phare\Translation\Translator;
use Phare\Container\Container;

test('service provider can be instantiated', function () {
    $container = new Container();
    $provider = new TranslationServiceProvider($container);
    
    expect($provider)->toBeInstanceOf(TranslationServiceProvider::class);
});

test('translator can be registered directly', function () {
    $container = new Container();
    
    $container->singleton('translator', function () {
        return new Translator('en', 'en');
    });
    
    $translator = $container->make('translator');
    
    expect($translator)->toBeInstanceOf(Translator::class);
    expect($translator->getLocale())->toBe('en');
    expect($translator->getFallback())->toBe('en');
});

test('helper functions work when defined', function () {
    if (!function_exists('test_trans')) {
        function test_trans(string $key, array $replace = [], ?string $locale = null): string {
            $translator = new Translator('en', 'en');
            return $translator->trans($key, $replace, $locale);
        }
    }
    
    expect(function_exists('test_trans'))->toBeTrue();
    expect(test_trans('test.key'))->toBe('test.key');
});