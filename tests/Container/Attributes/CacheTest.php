<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Cache;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    public function test_resolves_default_cache_when_no_arg(): void
    {
        $container = new Container();
        $manager = new \stdClass();
        $container->singleton('cache', fn () => $manager);

        $consumer = $container->make(CacheStubConsumer::class);

        $this->assertSame($manager, $consumer->cache);
    }

    public function test_resolves_named_store_via_cache_manager(): void
    {
        $container = new Container();
        $defaultStore = new \stdClass();
        $namedStore = new \stdClass();
        $namedStore->mark = 'redis';

        $managerSpy = new class($defaultStore, $namedStore)
        {
            public ?string $lastRequested = null;

            public function __construct(private object $default, private object $redis) {}

            public function store(?string $name = null): object
            {
                $this->lastRequested = $name;

                return $name === 'redis' ? $this->redis : $this->default;
            }
        };

        $container->singleton('cache.manager', fn () => $managerSpy);
        $container->singleton('cache', fn () => $defaultStore);

        $consumer = $container->make(CacheNamedStubConsumer::class);

        $this->assertSame($namedStore, $consumer->cache);
        $this->assertSame('redis', $managerSpy->lastRequested);
    }
}

class CacheStubConsumer
{
    public function __construct(#[Cache] public mixed $cache) {}
}

class CacheNamedStubConsumer
{
    public function __construct(#[Cache('redis')] public mixed $cache) {}
}
