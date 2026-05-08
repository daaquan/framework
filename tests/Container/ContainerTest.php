<?php

use Phare\Container\Container;
use Phare\Container\Exceptions\ContainerException;
use Phare\Http\Response;

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

it('resolving a class with dependencies does not corrupt the parent binding', function () {
    $container = new Container();

    class DepService {}

    class ParentClass
    {
        public function __construct(public DepService $dep) {}
    }

    $container->bind(ParentClass::class, ParentClass::class);

    $result = $container->make(ParentClass::class);

    expect($result)->toBeInstanceOf(ParentClass::class);
    expect($result)->not->toBeInstanceOf(DepService::class);

    // Resolve again to ensure binding is still correct
    $result2 = $container->make(ParentClass::class);
    expect($result2)->toBeInstanceOf(ParentClass::class);
});

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

it('autowires constructor when singleton maps interface to concrete', function () {
    $container = new Container();

    interface SingletonAutowireKernelContract {}

    class SingletonAutowireApp
    {
        public string $marker = 'app';
    }

    class SingletonAutowireKernel implements SingletonAutowireKernelContract
    {
        public function __construct(public SingletonAutowireApp $app) {}
    }

    $container->singleton(SingletonAutowireKernelContract::class, SingletonAutowireKernel::class);

    $kernel = $container->make(SingletonAutowireKernelContract::class);

    expect($kernel)->toBeInstanceOf(SingletonAutowireKernel::class);
    expect($kernel->app)->toBeInstanceOf(SingletonAutowireApp::class);
    expect($container->make(SingletonAutowireKernelContract::class))->toBe($kernel);
});

it('resolves singletons whose concrete is a Phalcon-reserved class without recursing', function () {
    // singleton('shortKey', ReservedAliasClass::class) used to recurse:
    // closure -> resolve(ReservedAliasClass) -> isAliasReserved -> make('shortKey') -> closure ...
    $container = new class() extends Container
    {
        public function __construct()
        {
            parent::__construct();
            // Add a custom reserved alias mapping for the test target.
            $this->reservedServices['recursionProbe'] = stdClass::class;
            $this->reservedServiceAlias[stdClass::class] = 'recursionProbe';
        }
    };

    $container->singleton('recursionProbe', stdClass::class);

    $instance = $container->make('recursionProbe');

    expect($instance)->toBeInstanceOf(stdClass::class);
    expect($container->make('recursionProbe'))->toBe($instance);
});

it('autowires classes whose constructor has untyped optional parameters', function () {
    // Inherited Phalcon\Http\Response::__construct has untyped optional params (`$code`, `$status`).
    // Resolution must use defaults instead of throwing on the missing type hint.
    $container = new Container();

    $container->singleton('response', Response::class);

    $response = $container->make('response');

    expect($response)->toBeInstanceOf(Response::class);
});
