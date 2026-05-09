<?php

declare(strict_types=1);

namespace Tests\Container;

use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class ContextualBindingEdgeTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_variadic_class_dep_with_give_tagged(): void
    {
        $this->container->bind(EdgeTransport::class, EdgeSlackTransport::class);
        $this->container->bind('email-transport', EdgeEmailTransport::class);
        $this->container->tag([EdgeTransport::class, 'email-transport'], 'transports');

        $this->container->when(EdgeNotifier::class)
            ->needs(EdgeTransport::class)
            ->giveTagged('transports');

        $instance = $this->container->make(EdgeNotifier::class);

        $this->assertCount(2, $instance->transports);
        $this->assertInstanceOf(EdgeSlackTransport::class, $instance->transports[0]);
        $this->assertInstanceOf(EdgeEmailTransport::class, $instance->transports[1]);
    }

    public function test_primitive_give_with_alias_mapped_abstract(): void
    {
        $this->container->bind(EdgeRegionAware::class, EdgeRegionConsumer::class);
        $this->container->alias(EdgeRegionAware::class, 'region.aware');

        $this->container->when('region.aware')
            ->needs('$region')
            ->give('us-east-1');

        $instance = $this->container->make(EdgeRegionConsumer::class);

        $this->assertSame('us-east-1', $instance->region);
    }

    public function test_rebinding_callback_fires_once_after_resolve(): void
    {
        $this->container->bind('rebind.target', fn () => new \stdClass());

        $count = 0;
        $this->container->rebinding('rebind.target', function ($app, $instance) use (&$count) {
            $count++;
        });

        $this->container->bind('rebind.target', fn () => new \stdClass());

        $this->assertSame(1, $count);
    }

    public function test_singleton_resolving_callbacks_fire_once_total(): void
    {
        $count = 0;
        $this->container->singleton(EdgeSingletonStub::class);
        $this->container->resolving(EdgeSingletonStub::class, function () use (&$count) {
            $count++;
        });

        $this->container->make(EdgeSingletonStub::class);
        $this->container->make(EdgeSingletonStub::class);

        $this->assertSame(1, $count);
    }
}

interface EdgeTransport {}
class EdgeSlackTransport implements EdgeTransport {}
class EdgeEmailTransport implements EdgeTransport {}

class EdgeNotifier
{
    /** @var array<int, EdgeTransport> */
    public array $transports;

    public function __construct(EdgeTransport ...$transports)
    {
        $this->transports = $transports;
    }
}

interface EdgeRegionAware {}

class EdgeRegionConsumer implements EdgeRegionAware
{
    public function __construct(public string $region) {}
}

class EdgeSingletonStub
{
    public function __construct() {}
}
