<?php

use Phare\Container\Container;
use Phare\Container\Exceptions\ContainerException;

it('can bind and resolve services', function () {
    $container = new Container();

    $container->bind('test', fn () => 'Hello, World!');

    expect($container->make('test'))->toBe('Hello, World!');
});

it('resolves the same instance for singletons', function () {
    $container = new Container();

    $container->singleton('singleton', fn () => new stdClass());

    $firstInstance = $container->make('singleton');
    $secondInstance = $container->make('singleton');

    expect($firstInstance)->toBe($secondInstance);
});

it('throws an exception for non-instantiable classes', function () {
    $container = new Container();

    $container->bind('nonInstantiable', DateTimeInterface::class);

    $container->make('nonInstantiable');
})->throws(ContainerException::class, 'is not instantiable');

it('can create an alias for a binding', function () {
    $container = new Container();

    $container->bind('original', fn () => 'Original Content');
    $container->alias('original', 'alias');

    expect($container->make('alias'))->toBe('Original Content');
});

it('can bind and resolve concrete implementations', function () {
    interface LoggerInterface {}

    class FileLogger implements LoggerInterface {}

    $container = new Container();

    $container->bind('LoggerInterface', 'FileLogger');

    $resolved = $container->make('LoggerInterface');

    expect($resolved)->toBeInstanceOf('FileLogger');
});

it('throws exception when aliasing to itself', function () {
    $container = new Container();

    $container->alias('original', 'original');
})->throws(LogicException::class);

it('resolves different instances for non-singletons', function () {
    $container = new Container();

    $container->bind('object', fn () => new stdClass());

    $firstInstance = $container->make('object');
    $secondInstance = $container->make('object');

    expect($firstInstance)->not->toBe($secondInstance);
});

it('resolves dependencies automatically', function () {
    $container = new Container();

    // Sample class with dependencies
    class A {}

    class B
    {
        public function __construct(public A $a) {}
    }

    $b = $container->make(B::class);

    expect($b)->toBeInstanceOf(B::class);
    expect($b->a)->toBeInstanceOf(A::class);
});

it('throws exception when a non-existent class is resolved', function () {
    $container = new Container();

    $container->bind('nonExistent', 'SomeNonExistentClass');

    $container->make('nonExistent');
})->throws(ContainerException::class);

it('throws exception when binding a non-instantiable interface without concrete implementation', function () {
    $container = new Container();

    interface SampleInterface {}

    $container->bind('SampleInterface', SampleInterface::class);

    $container->make('SampleInterface');
})->throws(ContainerException::class);

it('can alias a binding', function () {
    $container = new Container();

    $container->bind('original', fn () => 'Original Content');
    $container->alias('original', 'alias');

    expect($container->make('alias'))->toBe('Original Content');
});
