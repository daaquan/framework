<?php

use Phare\Container\Container;

class CallAndWrapTestTarget
{
    public function greet(string $name): string
    {
        return "hello {$name}";
    }

    public function withDep(Container $c, string $msg = 'd'): string
    {
        return $c instanceof Container ? "{$msg}-ok" : "{$msg}-fail";
    }
}

it('call() invokes a Class@method string with autowired dependencies', function () {
    $c = new Container();

    expect($c->call(CallAndWrapTestTarget::class . '@greet', ['name' => 'alice']))->toBe('hello alice');
});

it('call() invokes a Closure resolving typed deps from the container', function () {
    $c = new Container();
    $c->instance('greeting', 'hi');

    $result = $c->call(function (Container $self) {
        return $self->make('greeting');
    });

    expect($result)->toBe('hi');
});

it('call() invokes an array callable [$instance, "method"]', function () {
    $c = new Container();
    $obj = new CallAndWrapTestTarget();

    expect($c->call([$obj, 'greet'], ['name' => 'bob']))->toBe('hello bob');
});

it('call() honors named parameter overrides alongside autowired deps', function () {
    $c = new Container();
    $obj = new CallAndWrapTestTarget();

    expect($c->call([$obj, 'withDep'], ['msg' => 'override']))->toBe('override-ok');
});

it('wrap() returns a closure that defers call() execution', function () {
    $c = new Container();

    $wrapped = $c->wrap(function (Container $self) {
        return 'wrapped';
    });

    expect($wrapped)->toBeInstanceOf(Closure::class)
        ->and($wrapped())->toBe('wrapped');
});

it('factory() returns a closure that resolves the abstract on each call', function () {
    $c = new Container();
    $c->bind('thing', fn () => new stdClass());

    $factory = $c->factory('thing');

    $a = $factory();
    $b = $factory();

    expect($a)->toBeInstanceOf(stdClass::class)
        ->and($b)->toBeInstanceOf(stdClass::class)
        ->and($a)->not->toBe($b);
});

it('bindMethod() routes Class@method calls through the registered binding', function () {
    $c = new Container();
    $c->bindMethod(CallAndWrapTestTarget::class . '@greet', function ($target, $params) {
        return 'OVERRIDDEN ' . $target->greet($params['name'] ?? 'x');
    });

    expect($c->call(CallAndWrapTestTarget::class . '@greet', ['name' => 'eve']))->toBe('OVERRIDDEN hello eve');
});

it('hasMethodBinding() reports registered bindings only', function () {
    $c = new Container();
    $c->bindMethod('Foo@bar', fn () => null);

    expect($c->hasMethodBinding('Foo@bar'))->toBeTrue()
        ->and($c->hasMethodBinding('Foo@baz'))->toBeFalse();
});
