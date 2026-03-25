<?php

use Phalcon\Di\Di;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Application as ApplicationContract;
use Phare\Contracts\Foundation\Bus\Dispatchable;
use Phare\Contracts\Foundation\Bus\PendingDispatch;
use Phare\Queue\Job;
use Phare\Queue\QueueManager;

class DispatchableTestApplication extends Container implements ApplicationContract
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

class DispatchableQueueJob extends Job
{
    use Dispatchable;

    public bool $handled = false;

    public function __construct(public string $message = 'ok')
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->handled = true;
    }
}

beforeEach(function () {
    $this->app = new DispatchableTestApplication();
    $this->app->singleton(ApplicationContract::class, fn () => $this->app);
    $this->app[ApplicationContract::class] = $this->app;

    $this->queue = new QueueManager([
        'default' => 'database',
        'connections' => [
            'database' => ['driver' => 'database', 'queue' => 'default'],
        ],
    ]);
    $this->app->singleton('queue', fn () => $this->queue);
    $this->app['queue'] = $this->queue;

    Di::setDefault($this->app);
});

test('dispatchable dispatch returns pending dispatch and enqueues on resolve', function () {
    $pending = DispatchableQueueJob::dispatch('hello')->onQueue('critical');

    expect($pending)->toBeInstanceOf(PendingDispatch::class);

    $pending->resolve();

    /** @var \Phare\Queue\DatabaseQueue $connection */
    $connection = $this->queue->connection('database');
    $jobs = $connection->getJobs();

    expect($jobs)->toHaveCount(1);
    expect($jobs[0]['queue'])->toBe('critical');
});

test('dispatchable dispatchIf and dispatchUnless honor conditions', function () {
    $dispatchIfFalse = DispatchableQueueJob::dispatchIf(false, 'skip');
    $dispatchUnlessTrue = DispatchableQueueJob::dispatchUnless(true, 'skip');

    expect($dispatchIfFalse)->toBeNull();
    expect($dispatchUnlessTrue)->toBeNull();

    $dispatchIfTrue = DispatchableQueueJob::dispatchIf(true, 'run');
    $dispatchUnlessFalse = DispatchableQueueJob::dispatchUnless(false, 'run2');

    expect($dispatchIfTrue)->toBeInstanceOf(PendingDispatch::class);
    expect($dispatchUnlessFalse)->toBeInstanceOf(PendingDispatch::class);

    $dispatchIfTrue->resolve();
    $dispatchUnlessFalse->resolve();

    /** @var \Phare\Queue\DatabaseQueue $connection */
    $connection = $this->queue->connection('database');
    expect($connection->getJobs())->toHaveCount(2);
});

test('dispatchable dispatchSync executes handle immediately', function () {
    $result = DispatchableQueueJob::dispatchSync('sync');

    expect($result)->toBeNull();
});

