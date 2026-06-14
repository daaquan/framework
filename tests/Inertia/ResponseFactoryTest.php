<?php

use Phare\Inertia\LazyProp;
use Phare\Inertia\OptionalProp;
use Phare\Inertia\Response;
use Phare\Inertia\ResponseFactory;

it('renders a component into an Inertia Response', function () {
    $factory = new ResponseFactory();

    $response = $factory->render('Dashboard', ['user' => 'Ada']);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->component())->toBe('Dashboard')
        ->and($response->props())->toBe(['user' => 'Ada']);
});

it('stores and reads shared props', function () {
    $factory = new ResponseFactory();

    $factory->share('auth', ['user' => 'Ada']);

    expect($factory->getShared('auth'))->toBe(['user' => 'Ada'])
        ->and($factory->getShared())->toBe(['auth' => ['user' => 'Ada']]);
});

it('deep-merges shared props without clobbering sibling keys', function () {
    $factory = new ResponseFactory();

    $factory->share('auth', ['user' => 'Ada']);
    $factory->share('auth', ['team' => 'Core']);

    expect($factory->getShared('auth'))->toBe(['user' => 'Ada', 'team' => 'Core']);
});

it('sets and reads the asset version', function () {
    $factory = new ResponseFactory();

    $factory->version('abc123');

    expect($factory->getVersion())->toBe('abc123');
});

it('resolves a closure version lazily', function () {
    $factory = new ResponseFactory();

    $factory->version(fn () => 'lazy-version');

    expect($factory->getVersion())->toBe('lazy-version');
});

it('wraps lazy and optional props in markers', function () {
    $factory = new ResponseFactory();

    expect($factory->lazy(fn () => 'x'))->toBeInstanceOf(LazyProp::class)
        ->and($factory->optional(fn () => 'y'))->toBeInstanceOf(OptionalProp::class);
});

it('renders the root data-page element from a page array', function () {
    $page = ['component' => 'Dashboard', 'props' => ['a' => 1], 'url' => '/dash', 'version' => 'v1'];

    $html = ResponseFactory::renderRootElement($page);

    expect($html)->toContain('id="app"')
        ->and($html)->toContain('data-page=');

    // The data-page attribute must hold the page object as escaped JSON.
    preg_match('/data-page="([^"]*)"/', $html, $m);
    expect(json_decode(htmlspecialchars_decode($m[1]), true))->toBe($page);
});
