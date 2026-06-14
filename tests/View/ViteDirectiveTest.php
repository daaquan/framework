<?php

use Phare\Container\Container;
use Phare\View\ViewServiceProvider;
use Phare\View\Vite;

beforeEach(function () {
    $this->tmpViews = sys_get_temp_dir() . '/phare_vited_views_' . getmypid() . '_' . uniqid();
    $this->tmpStorage = sys_get_temp_dir() . '/phare_vited_storage_' . getmypid() . '_' . uniqid();
    $this->public = sys_get_temp_dir() . '/phare_vited_public_' . getmypid() . '_' . uniqid();
    @mkdir($this->tmpViews, 0777, true);
    @mkdir($this->tmpStorage . '/framework/views', 0777, true);
    @mkdir($this->public . '/build', 0777, true);
    file_put_contents($this->tmpViews . '/page.blade.php', "<head>@vite(['resources/css/app.css'])</head>");
    file_put_contents($this->public . '/build/manifest.json', json_encode([
        'resources/css/app.css' => ['file' => 'assets/app-xyz.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
    ]));

    $this->app = new Container();
    $this->app->instance('__views_path', $this->tmpViews);
    $this->app->instance('__storage_path', $this->tmpStorage . '/framework/views');
});

afterEach(function () {
    array_map('unlink', glob($this->tmpViews . '/*') ?: []);
    array_map('unlink', glob($this->tmpStorage . '/framework/views/*') ?: []);
    if (is_file($this->public . '/build/manifest.json')) {
        unlink($this->public . '/build/manifest.json');
    }
    @rmdir($this->tmpViews);
    @rmdir($this->tmpStorage . '/framework/views');
    @rmdir($this->tmpStorage . '/framework');
    @rmdir($this->tmpStorage);
    @rmdir($this->public . '/build');
    @rmdir($this->public);
});

it('binds a Vite instance to the container', function () {
    (new ViewServiceProvider($this->app))->register();

    expect($this->app->make('vite'))->toBeInstanceOf(Vite::class);
});

it('registers @vite and @viteReactRefresh directives that resolve the bound Vite', function () {
    (new ViewServiceProvider($this->app))->register();

    $blade = $this->app->make('view')->getEngine()->getBlade();

    expect($blade->compileString("@vite(['resources/css/app.css'])"))
        ->toContain("app('vite')")
        ->toContain("['resources/css/app.css']");

    expect($blade->compileString('@viteReactRefresh'))
        ->toContain("app('vite')->reactRefresh()");
});
