<?php

use Phare\Container\Container;
use Phare\Contracts\Foundation\Application as ApplicationContract;
use Phare\Eloquent\Concerns\HasEvents;
use Phare\Events\Dispatcher;

class EventModelTestApplication extends Container implements ApplicationContract
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

class EventedModel
{
    use HasEvents;

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
