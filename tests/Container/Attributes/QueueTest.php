<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Queue;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    public function test_resolves_default_connection_when_no_arg(): void
    {
        $container = new Container();
        $manager = new FakeQueueManager();
        $container->singleton('queue', fn () => $manager);

        $consumer = $container->make(QueueDefaultStubConsumer::class);

        $this->assertSame('default-queue', $consumer->queue);
    }

    public function test_resolves_named_connection_via_queue_manager(): void
    {
        $container = new Container();
        $manager = new FakeQueueManager();

        $container->singleton('queue.manager', fn () => $manager);
        $container->singleton('queue', fn () => new \stdClass());

        $consumer = $container->make(QueueNamedStubConsumer::class);

        $this->assertSame('redis-queue', $consumer->queue);
    }
}

class FakeQueueManager
{
    public function connection(?string $name = null): string
    {
        return $name === null ? 'default-queue' : "$name-queue";
    }
}

class QueueDefaultStubConsumer
{
    public function __construct(#[Queue] public mixed $queue) {}
}

class QueueNamedStubConsumer
{
    public function __construct(#[Queue('redis')] public mixed $queue) {}
}
