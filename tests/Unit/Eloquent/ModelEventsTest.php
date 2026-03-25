<?php

use Phare\Events\Dispatcher;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Application as ApplicationContract;

class EventModelTestApplication extends Container implements ApplicationContract
{
    public function version(): string
    {
        return 'test';
    }

    public function basePath(string $path = ''): string
    {
        return '/tmp'.$path;
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

    public function bootstrapWith(array $bootstrappers)
    {
        return null;
    }
}

class EventedModel
{
    use \Phare\Eloquent\Concerns\HasEvents;

    public function triggerEvent(string $event, bool $halt = false): mixed
    {
        return $this->fireModelEvent($event, $halt);
    }
}

beforeEach(function () {
    $this->app = new EventModelTestApplication();
    $this->dispatcher = new Dispatcher($this->app);
    EventedModel::setEventDispatcher($this->dispatcher);
});

afterEach(function () {
    EventedModel::unsetEventDispatcher();
});

it('registers and dispatches eloquent model events', function () {
    $called = false;

    EventedModel::creating(function (EventedModel $model) use (&$called) {
        $called = $model instanceof EventedModel;

        return 'ok';
    });

    $model = new EventedModel();
    $result = $model->triggerEvent('creating');

    expect($called)->toBeTrue();
    expect($result)->toBe(['ok']);
});

it('halts model events when listener returns false', function () {
    EventedModel::saving(fn () => false);
    EventedModel::saving(fn () => 'never');

    $model = new EventedModel();
    $result = $model->triggerEvent('saving', true);

    expect($result)->toBeFalse();
});
