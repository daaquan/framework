<?php

use Phare\Container\Container;
use Phare\Providers\ViewProvider;
use Phare\View\Factory;
use Phare\View\ViewServiceProvider;

beforeEach(function () {
    $this->tmpViews = sys_get_temp_dir() . '/phare_vsp_views_' . getmypid();
    $this->tmpStorage = sys_get_temp_dir() . '/phare_vsp_storage_' . getmypid();
    @mkdir($this->tmpViews, 0777, true);
    @mkdir($this->tmpStorage . '/framework/views', 0777, true);
    file_put_contents($this->tmpViews . '/greet.blade.php', 'Hi {{ $who }}');
    @mkdir($this->tmpViews . '/auth', 0777, true);
    file_put_contents($this->tmpViews . '/auth/login.blade.php', 'Login {{ $who }}');

    $this->app = new Container();
    $this->app->instance('__views_path', $this->tmpViews);
    // Mirror production: storagePath('framework/views') is the compiled-output dir.
    $this->app->instance('__storage_path', $this->tmpStorage . '/framework/views');
});

afterEach(function () {
    array_map('unlink', glob($this->tmpViews . '/auth/*') ?: []);
    @rmdir($this->tmpViews . '/auth');
    array_map('unlink', glob($this->tmpViews . '/*') ?: []);
    array_map('unlink', glob($this->tmpStorage . '/framework/views/*') ?: []);
    @rmdir($this->tmpViews);
    @rmdir($this->tmpStorage . '/framework/views');
    @rmdir($this->tmpStorage . '/framework');
    @rmdir($this->tmpStorage);
});

it('binds a functional Factory to the view slot', function () {
    (new ViewServiceProvider($this->app))->register();

    $factory = $this->app->make('view');
    expect($factory)->toBeInstanceOf(Factory::class);
    expect($factory->make('greet', ['who' => 'Sam'])->render())->toBe('Hi Sam');
});

it('renders nested (dot-notation) views through the BladeEngine bridge', function () {
    // Regression: Factory normalizes 'auth.login' to 'auth/login'; BladeOne is
    // dot-native and treats a slash-path as a literal with no extension, so the
    // engine must convert back to dots or nested views 404 ("Template not found").
    (new ViewServiceProvider($this->app))->register();

    $factory = $this->app->make('view');
    expect($factory->make('auth.login', ['who' => 'Sam'])->render())->toBe('Login Sam');
});

it('drops the redundant bare-Phalcon ViewProvider (A07 leak)', function () {
    // The canonical view provider is Phare\View\ViewServiceProvider. The old
    // Phare\Providers\ViewProvider only did singleton('view', Phalcon\Mvc\View::class)
    // with no engine wiring — a dead Phalcon-typed competitor for the 'view' slot.
    expect(class_exists(ViewProvider::class))->toBeFalse();
});
