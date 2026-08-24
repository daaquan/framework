<?php

use Phalcon\Di\Di;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Application as ApplicationContract;
use Phare\Events\Contracts\ShouldDispatchAfterCommit;
use Phare\Events\Dispatcher;
use Phare\Support\Facades\Event as EventFacade;

class EventTestApplication extends Container implements ApplicationContract
{
    public function version(): string
    {
        return 'test';
    }

    public function basePath(string $path = ''): string
    {
        return '/tmp' . $path;
    }

    public function bootstrapPath(string $path = ''): string
    {
        return '/tmp/bootstrap' . $path;
    }

    public function configPath(string $path = ''): string
    {
        return '/tmp/config' . $path;
    }

    public function databasePath(string $path = ''): string
    {
        return '/tmp/database' . $path;
    }

    public function languagePath(string $path = ''): string
    {
        return '/tmp/lang' . $path;
    }

    public function resourcePath(string $path = ''): string
    {
        return '/tmp/resources' . $path;
    }

    public function storagePath(string $path = ''): string
    {
        return '/tmp/storage' . $path;
    }

    public function routesIsCached(): bool
    {
        return false;
    }

    public function environment(...$environments)
    {
        return 'testing';
    }

    public function runningInConsole()
    {
        return true;
    }

    public function runningUnitTests()
    {
        return true;
    }

    public function bootstrapWith(array $bootstrappers) {}
}

class SampleEvent
{
    public function __construct(public string $name) {}
}

class SampleEventListener
{
    public function handle(SampleEvent $event): string
    {
        return 'handled:' . $event->name;
    }
}

class SampleEventSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            SampleEvent::class => 'onSampleEvent',
        ];
    }

    public function onSampleEvent(SampleEvent $event): string
    {
        return 'subscriber:' . $event->name;
    }
}

class DeferredSampleEvent implements ShouldDispatchAfterCommit
{
    public function __construct(public string $name) {}
}

class CallbackTransactionManager
{
    public array $callbacks = [];

    public function addCallback(Closure $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function commit(): void
    {
        foreach ($this->callbacks as $callback) {
            $callback();
        }

        $this->callbacks = [];
    }
}

beforeEach(function () {
    $this->app = new EventTestApplication();
    $this->dispatcher = new Dispatcher($this->app);

    $this->app->singleton(ApplicationContract::class, fn () => $this->app);
    $this->app->singleton('events', fn () => $this->dispatcher);
    $this->app[ApplicationContract::class] = $this->app;
    $this->app['events'] = $this->dispatcher;

    Di::setDefault($this->app->phalconDi());
    EventFacade::setFacadeApplication($this->app);
});

test('it dispatches object events to listeners', function () {
    $this->dispatcher->listen(SampleEvent::class, function (SampleEvent $event) {
        return strtoupper($event->name);
    });

    $result = $this->dispatcher->dispatch(new SampleEvent('phare'));

    expect($result)->toBe(['PHARE']);
});

test('until returns first non null response', function () {
    $this->dispatcher->listen('sample.event', fn () => null);
    $this->dispatcher->listen('sample.event', fn () => 'resolved');
    $this->dispatcher->listen('sample.event', fn () => 'ignored');

    $result = $this->dispatcher->until('sample.event');

    expect($result)->toBe('resolved');
});

test('wildcard listeners receive event name and payload array', function () {
    $this->dispatcher->listen('user.*', function (string $eventName, array $payload) {
        return $eventName . '-' . $payload[0];
    });

    $result = $this->dispatcher->dispatch('user.created', ['42']);

    expect($result)->toBe(['user.created-42']);
});

test('push and flush dispatch queued event payload', function () {
    $this->dispatcher->listen('jobs.created', fn (string $name) => 'job:' . $name);
    $this->dispatcher->push('jobs.created', ['alpha']);

    $result = $this->dispatcher->flush('jobs.created');

    expect($result)->toBeNull();
});

test('forget pushed removes queued listeners', function () {
    $called = 0;

    $this->dispatcher->listen('jobs.created', function () use (&$called) {
        $called++;
    });

    $this->dispatcher->push('jobs.created');
    $this->dispatcher->forgetPushed();
    $this->dispatcher->flush('jobs.created');

    expect($called)->toBe(0);
});

test('it resolves class string listeners through container', function () {
    $this->dispatcher->listen(SampleEvent::class, SampleEventListener::class);

    $result = $this->dispatcher->dispatch(new SampleEvent('container'));

    expect($result)->toBe(['handled:container']);
});

test('it subscribes subscriber mappings', function () {
    $this->dispatcher->subscribe(SampleEventSubscriber::class);

    $result = $this->dispatcher->dispatch(new SampleEvent('subscribed'));

    expect($result)->toBe(['subscriber:subscribed']);
});

test('event helper dispatches through events service', function () {
    $this->dispatcher->listen(SampleEvent::class, fn (SampleEvent $event) => 'helper:' . $event->name);

    $result = event(new SampleEvent('ok'));

    expect($result)->toBe(['helper:ok']);
});

test('event facade dispatches through bound dispatcher', function () {
    $this->dispatcher->listen(SampleEvent::class, fn (SampleEvent $event) => 'facade:' . $event->name);

    $result = EventFacade::dispatch(new SampleEvent('ok'));

    expect($result)->toBe(['facade:ok']);
});

test('it infers event types from closure listener parameter', function () {
    $this->dispatcher->listen(function (SampleEvent $event) {
        return 'closure:' . $event->name;
    });

    $result = $this->dispatcher->dispatch(new SampleEvent('typed'));

    expect($result)->toBe(['closure:typed']);
});

test('event helper forwards halt dispatch argument', function () {
    $this->dispatcher->listen('sample.halt', fn () => null);
    $this->dispatcher->listen('sample.halt', fn () => 'first');
    $this->dispatcher->listen('sample.halt', fn () => 'second');

    $result = event('sample.halt', [], true);

    expect($result)->toBe('first');
});

test('dispatchIf dispatches only when condition is true', function () {
    $called = 0;
    $this->dispatcher->listen('sample.conditional', function () use (&$called) {
        $called++;

        return 'ok';
    });

    $falseResult = $this->dispatcher->dispatchIf(false, 'sample.conditional');
    $trueResult = $this->dispatcher->dispatchIf(true, 'sample.conditional');

    expect($falseResult)->toBe([]);
    expect($trueResult)->toBe(['ok']);
    expect($called)->toBe(1);
});

test('dispatchUnless dispatches only when condition is false', function () {
    $called = 0;
    $this->dispatcher->listen('sample.unless', function () use (&$called) {
        $called++;

        return 'ran';
    });

    $trueResult = $this->dispatcher->dispatchUnless(true, 'sample.unless');
    $falseResult = $this->dispatcher->dispatchUnless(false, 'sample.unless');

    expect($trueResult)->toBe([]);
    expect($falseResult)->toBe(['ran']);
    expect($called)->toBe(1);
});

test('it defers should dispatch after commit events until transaction commit', function () {
    $transactions = new CallbackTransactionManager();
    $this->dispatcher->setTransactionManagerResolver(fn () => $transactions);

    $called = 0;
    $this->dispatcher->listen(DeferredSampleEvent::class, function (DeferredSampleEvent $event) use (&$called) {
        $called++;

        return $event->name;
    });

    $result = $this->dispatcher->dispatch(new DeferredSampleEvent('deferred'));
    expect($result)->toBeNull();
    expect($called)->toBe(0);
    expect($transactions->callbacks)->toHaveCount(1);

    $transactions->commit();

    expect($called)->toBe(1);
});

test('it can defer all events within callback', function () {
    $captured = [];

    $this->dispatcher->listen('sample.deferred', function (string $value) use (&$captured) {
        $captured[] = $value;
    });

    $this->dispatcher->defer(function () use (&$captured) {
        $this->dispatcher->dispatch('sample.deferred', ['first']);
        expect($captured)->toBe([]);
        $this->dispatcher->dispatch('sample.deferred', ['second']);
    });

    expect($captured)->toBe(['first', 'second']);
});

test('it can defer only selected events', function () {
    $captured = [];

    $this->dispatcher->listen('sample.delayed', function (string $value) use (&$captured) {
        $captured[] = "delayed:{$value}";
    });

    $this->dispatcher->listen('sample.instant', function (string $value) use (&$captured) {
        $captured[] = "instant:{$value}";
    });

    $this->dispatcher->defer(function () use (&$captured) {
        $this->dispatcher->dispatch('sample.instant', ['a']);
        $this->dispatcher->dispatch('sample.delayed', ['b']);
        expect($captured)->toBe(['instant:a']);
    }, ['sample.delayed']);

    expect($captured)->toBe(['instant:a', 'delayed:b']);
});

test('dispatch treats null payload as empty payload', function () {
    $this->dispatcher->listen('sample.null-payload', fn () => 'ok');

    $result = $this->dispatcher->dispatch('sample.null-payload', null);

    expect($result)->toBe(['ok']);
});

test('nested defer preserves parent deferred queue', function () {
    $captured = [];

    $this->dispatcher->listen('sample.outer-first', function () use (&$captured) {
        $captured[] = 'outer-first';
    });

    $this->dispatcher->listen('sample.inner', function () use (&$captured) {
        $captured[] = 'inner';
    });

    $this->dispatcher->listen('sample.outer-last', function () use (&$captured) {
        $captured[] = 'outer-last';
    });

    $this->dispatcher->defer(function () use (&$captured) {
        $this->dispatcher->dispatch('sample.outer-first');

        $this->dispatcher->defer(function () use (&$captured) {
            $this->dispatcher->dispatch('sample.inner');
            expect($captured)->toBe([]);
        });

        expect($captured)->toBe(['inner']);

        $this->dispatcher->dispatch('sample.outer-last');
        expect($captured)->toBe(['inner']);
    });

    expect($captured)->toBe(['inner', 'outer-first', 'outer-last']);
});
