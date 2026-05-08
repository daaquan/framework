<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Cache;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    public function test_resolves_cache_manager(): void
    {
        $container = new Container();
        $manager = new \stdClass();
        $container->singleton('cache', fn () => $manager);

        $consumer = $container->make(CacheStubConsumer::class);

        $this->assertSame($manager, $consumer->cache);
    }
}

class CacheStubConsumer
{
    public function __construct(#[Cache] public mixed $cache) {}
}
