<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Hash;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class HashTest extends TestCase
{
    public function test_resolves_default_driver_when_no_arg(): void
    {
        $container = new Container();
        $manager = new FakeHashManager();
        $container->singleton('hash', fn () => $manager);

        $consumer = $container->make(HashDefaultStubConsumer::class);

        $this->assertSame($manager, $consumer->hasher);
    }

    public function test_resolves_named_driver_via_hash_manager(): void
    {
        $container = new Container();
        $manager = new FakeHashManager();

        $container->singleton('hash.manager', fn () => $manager);
        $container->singleton('hash', fn () => new \stdClass());

        $consumer = $container->make(HashNamedStubConsumer::class);

        $this->assertSame('argon-driver', $consumer->hasher);
    }
}

class FakeHashManager
{
    public function driver(?string $name = null): string
    {
        return $name === null ? 'default-driver' : "$name-driver";
    }
}

class HashDefaultStubConsumer
{
    public function __construct(#[Hash] public mixed $hasher) {}
}

class HashNamedStubConsumer
{
    public function __construct(#[Hash('argon')] public mixed $hasher) {}
}
