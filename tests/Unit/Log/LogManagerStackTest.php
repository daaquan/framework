<?php

use Phalcon\Config\Config;
use Phalcon\Di\Di;
use Phare\Log\LogManager;
use Psr\Log\LoggerInterface;

/**
 * Build a Phalcon Di container with a `config` service that exposes the given
 * logging config block.  LogManager only needs $app['config'] + runningUnitTests().
 * runningUnitTests() is not on DiInterface, so we subclass Di to provide it.
 */
function makeLogManagerStubApp(array $logging): Di
{
    $config = new Config(['logging' => $logging]);

    $di = new class() extends Di
    {
        public function runningUnitTests(): bool
        {
            return true;
        }
    };
    $di->setShared('config', $config);

    return $di;
}

beforeEach(function () {
    $this->app = makeLogManagerStubApp([
        'default' => 'single',
        'channels' => [
            'single' => [
                'driver' => 'single',
                'path' => sys_get_temp_dir() . '/phare-stack-test.log',
                'level' => 'debug',
            ],
            'noop' => [
                'driver' => 'noop',
                'level' => 'debug',
            ],
        ],
    ]);
});

it('returns the same instance from channel() and driver()', function () {
    $manager = new LogManager($this->app);

    $viaDriver = $manager->driver('noop');
    $viaChannel = $manager->channel('noop');

    expect($viaChannel)->toBe($viaDriver);
});

it('returns the default channel when channel() called with null', function () {
    $manager = new LogManager($this->app);

    expect($manager->channel())->toBe($manager->driver());
});

it('stack() aggregates multiple channels into one logger', function () {
    $manager = new LogManager($this->app);

    $stack = $manager->stack(['single', 'noop']);

    expect($stack)->toBeInstanceOf(LoggerInterface::class);

    $adapters = $stack->getAdapters();
    expect(count($adapters))->toBeGreaterThanOrEqual(2);
});

it('stack() caches by named channel argument', function () {
    $manager = new LogManager($this->app);

    $a = $manager->stack(['single', 'noop'], 'audit');
    $b = $manager->stack(['single', 'noop'], 'audit');

    expect($a)->toBe($b);
});

it('stack() returns fresh instance when channel name is null', function () {
    $manager = new LogManager($this->app);

    $a = $manager->stack(['noop']);
    $b = $manager->stack(['noop']);

    expect($a)->not->toBe($b);
});

it('resolves stack channels via #[Log] config when driver is stack', function () {
    $app = makeLogManagerStubApp([
        'default' => 'audit',
        'channels' => [
            'audit' => [
                'driver' => 'stack',
                'channels' => ['noop'],
            ],
            'noop' => [
                'driver' => 'noop',
                'level' => 'debug',
            ],
        ],
    ]);

    $manager = new LogManager($app);

    $logger = $manager->driver('audit');

    expect($logger)->toBeInstanceOf(LoggerInterface::class);
});
