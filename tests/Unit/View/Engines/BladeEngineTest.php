<?php

use Phare\Contracts\View\Engine;
use Phare\View\Blade;
use Phare\View\Engines\BladeEngine;

beforeEach(function () {
    $this->tmpViews = sys_get_temp_dir() . '/phare_views_' . getmypid();
    $this->tmpCompiled = sys_get_temp_dir() . '/phare_compiled_' . getmypid();
    @mkdir($this->tmpViews, 0777, true);
    @mkdir($this->tmpCompiled, 0777, true);
    file_put_contents($this->tmpViews . '/hello.blade.php', 'Hello {{ $name }}!');
});

afterEach(function () {
    array_map('unlink', glob($this->tmpViews . '/*') ?: []);
    array_map('unlink', glob($this->tmpCompiled . '/*') ?: []);
    @rmdir($this->tmpViews);
    @rmdir($this->tmpCompiled);
});

it('renders a blade template through the BladeOne engine', function () {
    $blade = new Blade($this->tmpViews, $this->tmpCompiled, Blade::MODE_DEBUG);
    $engine = new BladeEngine($blade);

    expect($engine->render('hello', ['name' => 'World']))->toBe('Hello World!');
});

it('is a View Engine', function () {
    $engine = new BladeEngine(new Blade($this->tmpViews, $this->tmpCompiled));
    expect($engine)->toBeInstanceOf(Engine::class);
});
