<?php

use Phalcon\Di\Di;
use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Foundation\Bootstrap\LoadConfiguration;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\Bootstrap\RegisterFacades;
use Phare\Foundation\Bootstrap\RegisterProviders;
use Phare\View\Factory;
use Phare\View\View;

function refreshViewHelperApp(): void
{
    Di::reset();

    $_ENV['APP_BASE_PATH'] = 'tests/Mock';
    $app = require $_ENV['APP_BASE_PATH'] . '/bootstrap/app.php';
    $app->bootstrapWith([
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterProviders::class,
        RegisterFacades::class,
    ]);

    Di::setDefault($app);
}

beforeEach(function () {
    refreshViewHelperApp();
});

test('view() with no arguments returns the Factory (canonical stack)', function () {
    expect(view())->toBeInstanceOf(Factory::class);
});

test('view($name, $data) returns a renderable View carrying name + data', function () {
    $view = view('greet', ['who' => 'Sam']);

    expect($view)->toBeInstanceOf(View::class);
    expect($view->getView())->toBe('greet');
    expect($view->get('who'))->toBe('Sam');
});
