<?php

use Phare\Container\Container;
use Phare\Inertia\InertiaServiceProvider;
use Phare\Inertia\ResponseFactory;
use Phare\Support\Facades\Inertia;
use Phare\View\ViewServiceProvider;

beforeEach(function () {
    $this->tmpViews = sys_get_temp_dir() . '/phare_inertia_views_' . getmypid();
    $this->tmpStorage = sys_get_temp_dir() . '/phare_inertia_storage_' . getmypid();
    @mkdir($this->tmpViews, 0777, true);
    @mkdir($this->tmpStorage . '/framework/views', 0777, true);
    file_put_contents($this->tmpViews . '/app.blade.php', '<html>@inertia</html>');

    $this->app = new Container();
    $this->app->instance('__views_path', $this->tmpViews);
    $this->app->instance('__storage_path', $this->tmpStorage . '/framework/views');
});

afterEach(function () {
    array_map('unlink', glob($this->tmpViews . '/*') ?: []);
    array_map('unlink', glob($this->tmpStorage . '/framework/views/*') ?: []);
    @rmdir($this->tmpViews);
    @rmdir($this->tmpStorage . '/framework/views');
    @rmdir($this->tmpStorage . '/framework');
    @rmdir($this->tmpStorage);
});

it('binds inertia as a ResponseFactory singleton', function () {
    (new InertiaServiceProvider($this->app))->register();

    $a = $this->app->make('inertia');
    $b = $this->app->make('inertia');

    expect($a)->toBeInstanceOf(ResponseFactory::class)
        ->and($a)->toBe($b);
});

it('compiles the @inertia directive into the root data-page element', function () {
    (new ViewServiceProvider($this->app))->register();
    $provider = new InertiaServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $page = ['component' => 'Dashboard', 'props' => [], 'url' => '/', 'version' => 'v1'];
    $html = $this->app->make('view')->make('app', ['page' => $page])->render();

    expect($html)->toContain('<div id="app"></div>')
        ->and($html)->toContain('<script type="application/json" data-page="app">');
});

it('exposes the inertia accessor on the facade', function () {
    $method = new ReflectionMethod(Inertia::class, 'getFacadeAccessor');
    $method->setAccessible(true);

    expect($method->invoke(null))->toBe('inertia');
});
