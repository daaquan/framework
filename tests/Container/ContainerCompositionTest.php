<?php

use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phalcon\Encryption\Security;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Application;
use Phare\Foundation\AbstractApplication;
use Phare\Foundation\Bootstrap\LoadConfiguration;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\Bootstrap\RegisterFacades;
use Phare\Foundation\Bootstrap\RegisterProviders;
use Phare\Foundation\Micro;

it('basic bind and make returns concrete', function () {
    $c = new Container();
    $c->bind('foo', fn () => new stdClass());

    expect($c->make('foo'))->toBeInstanceOf(stdClass::class);
});

it('singleton returns same instance', function () {
    $c = new Container();
    $c->singleton('foo', fn () => new stdClass());

    expect($c->make('foo'))->toBe($c->make('foo'));
});

it('bind default is not shared', function () {
    $c = new Container();
    $c->bind('foo', fn () => new stdClass());

    expect($c->make('foo'))->not->toBe($c->make('foo'));
});

it('closure singleton returning array bypasses phalcon store', function () {
    $c = new Container();
    $c->singleton('arr', fn () => ['a' => 1]);

    expect($c->make('arr'))->toBe(['a' => 1]);
    expect($c->make('arr'))->toBe($c->make('arr'));
});

it('alias resolves to target', function () {
    $c = new Container();
    $c->bind('foo', fn () => new stdClass());
    $c->alias('foo', 'bar');

    expect($c->make('bar'))->toBeInstanceOf(stdClass::class);
});

it('is shared reflects binding', function () {
    $c = new Container();
    $c->singleton('s', fn () => new stdClass());
    $c->bind('b', fn () => new stdClass());

    expect($c->isShared('s'))->toBeTrue();
    expect($c->isShared('b'))->toBeFalse();
});

it('is not a phalcon di interface', function () {
    // Phalcon's container contract is no longer part of Phare's public API;
    // components that need one are handed phalconDi() instead.
    expect(new Container())->not->toBeInstanceOf(DiInterface::class);
});

it('getshared delegates to phalcon store', function () {
    $c = new Container();
    $c->set('svc', fn () => new stdClass(), true);

    expect($c->getShared('svc'))->toBeInstanceOf(stdClass::class);
});

it('exposes inner phalcon di accessor', function () {
    $c = new Container();
    expect($c->phalconDi())->toBeInstanceOf(DiInterface::class);
});

it('no longer extends the Phalcon Di class', function () {
    $parent = (new ReflectionClass(Container::class))->getParentClass();
    expect($parent)->toBeFalse();
});

it('holds a distinct inner Phalcon Di instance', function () {
    $c = new Container();
    expect($c->phalconDi())->not->toBe($c);
    expect($c->phalconDi())->toBeInstanceOf(Di::class);
});

it('still satisfies ArrayAccess (load-bearing for app() helper)', function () {
    expect(new Container())->toBeInstanceOf(ArrayAccess::class);
});

function bootCompositionApp(): AbstractApplication
{
    Di::reset();
    $_ENV['APP_BASE_PATH'] = 'tests/Mock';
    $app = new Micro('tests/Mock');
    $app->bootstrapWith([
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
        RegisterProviders::class,
        RegisterFacades::class,
    ]);

    return $app;
}

it('hands phalcon components a phalcon container, not itself', function () {
    // The whole point of dropping implements DiInterface: a Phalcon component
    // that needs a container gets the inner store, and still resolves the
    // services the Phare container registered.
    $app = bootCompositionApp();

    expect($app)->not->toBeInstanceOf(DiInterface::class)
        ->and($app->phalconDi())->toBeInstanceOf(DiInterface::class)
        ->and(Di::getDefault())->toBe($app->phalconDi());

    $security = new Security();
    $security->setDI($app->phalconDi());

    expect($security->getDI())->toBe($app->phalconDi());
});

it('keeps the application resolvable through the phalcon store', function () {
    // app() and container() go through Di::getDefault(), which is now the
    // store. It must still hand back the Phare application.
    $app = bootCompositionApp();

    expect(app())->toBe($app)
        ->and(Di::getDefault()->getShared(Application::class))->toBe($app);
});
