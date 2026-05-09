<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Broadcast;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class BroadcastTest extends TestCase
{
    public function test_resolves_default_driver_when_no_arg(): void
    {
        $container = new Container();
        $manager = new FakeBroadcastManager();
        $container->singleton('broadcast', fn () => $manager);

        $consumer = $container->make(BroadcastDefaultStubConsumer::class);

        $this->assertSame('default-broadcaster', $consumer->broadcaster);
    }

    public function test_resolves_named_driver_via_broadcast_manager(): void
    {
        $container = new Container();
        $manager = new FakeBroadcastManager();

        $container->singleton('broadcast.manager', fn () => $manager);
        $container->singleton('broadcast', fn () => new \stdClass());

        $consumer = $container->make(BroadcastNamedStubConsumer::class);

        $this->assertSame('pusher-broadcaster', $consumer->broadcaster);
    }
}

class FakeBroadcastManager
{
    public function driver(?string $name = null): string
    {
        return $name === null ? 'default-broadcaster' : "$name-broadcaster";
    }
}

class BroadcastDefaultStubConsumer
{
    public function __construct(#[Broadcast] public mixed $broadcaster) {}
}

class BroadcastNamedStubConsumer
{
    public function __construct(#[Broadcast('pusher')] public mixed $broadcaster) {}
}
