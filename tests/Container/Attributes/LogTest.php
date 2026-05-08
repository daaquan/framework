<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Log;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class LogTest extends TestCase
{
    public function test_resolves_default_log_driver_when_no_arg(): void
    {
        $container = new Container();
        $manager = new FakeLogManager();
        $container->singleton('log', fn () => $manager);

        $consumer = $container->make(LogDefaultStubConsumer::class);

        $this->assertSame('default-driver', $consumer->log);
    }

    public function test_resolves_named_log_driver(): void
    {
        $container = new Container();
        $manager = new FakeLogManager();
        $container->singleton('log', fn () => $manager);

        $consumer = $container->make(LogNamedStubConsumer::class);

        $this->assertSame('stack-driver', $consumer->log);
    }
}

class FakeLogManager
{
    public function driver($name = null): string
    {
        return $name === null ? 'default-driver' : "$name-driver";
    }
}

class LogDefaultStubConsumer
{
    public function __construct(#[Log] public mixed $log) {}
}

class LogNamedStubConsumer
{
    public function __construct(#[Log('stack')] public mixed $log) {}
}
