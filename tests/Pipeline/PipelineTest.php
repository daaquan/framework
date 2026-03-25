<?php

use Phare\Container\Container;
use Phare\Pipeline\Pipeline;

beforeEach(function () {
    $this->container = new Container();
});

test('pipeline sends object through closures', function () {
    $result = (new Pipeline($this->container))
        ->send('hello')
        ->through([
            function ($passable, $next) {
                return $next($passable . ' world');
            },
            function ($passable, $next) {
                return $next($passable . '!');
            },
        ])
        ->then(function ($passable) {
            return $passable;
        });

    expect($result)->toBe('hello world!');
});

test('pipeline thenReturn returns passable', function () {
    $result = (new Pipeline($this->container))
        ->send('value')
        ->through([])
        ->thenReturn();

    expect($result)->toBe('value');
});

test('pipeline works with no pipes', function () {
    $result = (new Pipeline($this->container))
        ->send('unchanged')
        ->through([])
        ->then(fn ($passable) => $passable);

    expect($result)->toBe('unchanged');
});

test('pipeline via changes the method called on pipe objects', function () {
    $pipe = new class()
    {
        public function customMethod($passable, $next)
        {
            return $next($passable . ' custom');
        }
    };

    $result = (new Pipeline($this->container))
        ->send('hello')
        ->through([$pipe])
        ->via('customMethod')
        ->then(fn ($passable) => $passable);

    expect($result)->toBe('hello custom');
});

test('pipeline resolves pipe classes from container', function () {
    $this->container->bind(PipelineTestPipe::class, PipelineTestPipe::class);

    $result = (new Pipeline($this->container))
        ->send('start')
        ->through([PipelineTestPipe::class])
        ->then(fn ($passable) => $passable);

    expect($result)->toBe('start piped');
});

test('pipeline handles pipe objects directly', function () {
    $pipe = new PipelineTestPipe();

    $result = (new Pipeline($this->container))
        ->send('start')
        ->through([$pipe])
        ->then(fn ($passable) => $passable);

    expect($result)->toBe('start piped');
});

test('pipeline preserves pipe execution order', function () {
    $order = [];

    $pipe1 = function ($passable, $next) use (&$order) {
        $order[] = 'pipe1_before';
        $result = $next($passable);
        $order[] = 'pipe1_after';

        return $result;
    };

    $pipe2 = function ($passable, $next) use (&$order) {
        $order[] = 'pipe2_before';
        $result = $next($passable);
        $order[] = 'pipe2_after';

        return $result;
    };

    (new Pipeline($this->container))
        ->send('test')
        ->through([$pipe1, $pipe2])
        ->then(fn ($passable) => $passable);

    expect($order)->toBe(['pipe1_before', 'pipe2_before', 'pipe2_after', 'pipe1_after']);
});

test('pipeline can parse string pipes with parameters', function () {
    $this->container->bind(PipelineTestPipeWithParams::class, PipelineTestPipeWithParams::class);

    $result = (new Pipeline($this->container))
        ->send('start')
        ->through([PipelineTestPipeWithParams::class . ':foo,bar'])
        ->then(fn ($passable) => $passable);

    expect($result)->toBe('start foo bar');
});

test('pipeline pipe method adds pipes', function () {
    $result = (new Pipeline($this->container))
        ->send('a')
        ->through([
            fn ($passable, $next) => $next($passable . 'b'),
        ])
        ->pipe([
            fn ($passable, $next) => $next($passable . 'c'),
        ])
        ->then(fn ($passable) => $passable);

    expect($result)->toBe('abc');
});

test('pipeline throws exception without container when resolving string pipe', function () {
    $pipeline = new Pipeline();

    expect(fn () => $pipeline
        ->send('test')
        ->through([PipelineTestPipe::class])
        ->thenReturn()
    )->toThrow(RuntimeException::class, 'A container instance has not been passed to the Pipeline.');
});

test('pipeline through accepts variadic arguments', function () {
    $pipe1 = fn ($passable, $next) => $next($passable . '1');
    $pipe2 = fn ($passable, $next) => $next($passable . '2');

    $result = (new Pipeline($this->container))
        ->send('x')
        ->through($pipe1, $pipe2)
        ->thenReturn();

    expect($result)->toBe('x12');
});

test('pipeline exceptions propagate', function () {
    expect(fn () => (new Pipeline($this->container))
        ->send('test')
        ->through([
            function ($passable, $next) {
                throw new InvalidArgumentException('pipe error');
            },
        ])
        ->thenReturn()
    )->toThrow(InvalidArgumentException::class, 'pipe error');
});

test('pipeline finally callback is called', function () {
    $finallyCalled = false;

    (new Pipeline($this->container))
        ->send('test')
        ->through([])
        ->finally(function ($passable) use (&$finallyCalled) {
            $finallyCalled = true;
        })
        ->then(fn ($passable) => $passable);

    expect($finallyCalled)->toBeTrue();
});

test('pipeline finally callback is called even on exception', function () {
    $finallyCalled = false;

    try {
        (new Pipeline($this->container))
            ->send('test')
            ->through([
                function ($passable, $next) {
                    throw new RuntimeException('fail');
                },
            ])
            ->finally(function () use (&$finallyCalled) {
                $finallyCalled = true;
            })
            ->then(fn ($passable) => $passable);
    } catch (RuntimeException) {
        // expected
    }

    expect($finallyCalled)->toBeTrue();
});

test('pipeline setContainer works', function () {
    $pipeline = new Pipeline();
    $pipeline->setContainer($this->container);

    $this->container->bind(PipelineTestPipe::class, PipelineTestPipe::class);

    $result = $pipeline
        ->send('start')
        ->through([PipelineTestPipe::class])
        ->thenReturn();

    expect($result)->toBe('start piped');
});

// Test helper classes
class PipelineTestPipe
{
    public function handle($passable, $next)
    {
        return $next($passable . ' piped');
    }
}

class PipelineTestPipeWithParams
{
    public function handle($passable, $next, $param1, $param2)
    {
        return $next($passable . ' ' . $param1 . ' ' . $param2);
    }
}
