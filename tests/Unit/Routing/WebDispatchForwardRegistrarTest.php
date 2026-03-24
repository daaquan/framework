<?php

use Phare\Routing\WebDispatchForwardRegistrar;

class FakeDispatchEventsManager
{
    /** @var array<string, callable> */
    public array $handlers = [];

    public function attach(string $eventName, callable $handler): void
    {
        $this->handlers[$eventName] = $handler;
    }
}

class FakeDispatchDispatcher
{
    public bool $forwarded = false;
    public array $forwardPayloads = [];

    public function __construct(private bool $alreadyForwarded = false)
    {
    }

    public function wasForwarded(): bool
    {
        return $this->alreadyForwarded;
    }

    public function forward(array $payload): void
    {
        $this->forwarded = true;
        $this->forwardPayloads[] = $payload;
    }
}

it('registers and forwards with payload when dispatcher was not forwarded', function () {
    $registrar = new WebDispatchForwardRegistrar();
    $events = new FakeDispatchEventsManager();
    $dispatcher = new FakeDispatchDispatcher(false);

    $registrar->register(
        $events,
        ['controller' => 'User'],
        ['id' => '1'],
        fn (array $routeData, array $urlParams) => ['params' => [$urlParams['id']]]
    );

    expect(isset($events->handlers['dispatch:beforeExecuteRoute']))->toBeTrue();
    $handler = $events->handlers['dispatch:beforeExecuteRoute'];
    $handler(null, $dispatcher);

    expect($dispatcher->forwarded)->toBeTrue();
    expect($dispatcher->forwardPayloads)->toBe([['params' => ['1']]]);
});

it('does not forward when dispatcher already forwarded', function () {
    $registrar = new WebDispatchForwardRegistrar();
    $events = new FakeDispatchEventsManager();
    $dispatcher = new FakeDispatchDispatcher(true);

    $registrar->register(
        $events,
        ['controller' => 'User'],
        ['id' => '1'],
        fn (array $routeData, array $urlParams) => ['params' => [$urlParams['id']]]
    );

    $handler = $events->handlers['dispatch:beforeExecuteRoute'];
    $handler(null, $dispatcher);

    expect($dispatcher->forwarded)->toBeFalse();
    expect($dispatcher->forwardPayloads)->toBe([]);
});
