<?php

declare(strict_types=1);

namespace Tests\Container;

use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute as ContextualAttributeContract;
use PHPUnit\Framework\TestCase;

class AfterResolvingAttributeTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_register_callback_stores_under_attribute_class(): void
    {
        $callback = function ($attribute, $object, $container) {};

        $this->container->afterResolvingAttribute(TestContextualAttribute::class, $callback);

        $reflection = new \ReflectionClass($this->container);
        $prop = $reflection->getProperty('afterResolvingAttributeCallbacks');
        $prop->setAccessible(true);
        $registry = $prop->getValue($this->container);

        $this->assertArrayHasKey(TestContextualAttribute::class, $registry);
        $this->assertSame([$callback], $registry[TestContextualAttribute::class]);
    }

    public function test_fire_invokes_registered_callback_with_attribute_instance_object_container(): void
    {
        $captured = null;
        $this->container->afterResolvingAttribute(
            TestContextualAttribute::class,
            function ($attribute, $object, $container) use (&$captured) {
                $captured = [$attribute, $object, $container];
            }
        );

        $reflection = new \ReflectionClass(StubWithTestAttribute::class);
        $attributes = $reflection->getAttributes();

        $sentinel = new \stdClass();
        $sentinel->id = 'resolved-object';

        $fire = (new \ReflectionMethod($this->container, 'fireAfterResolvingAttributeCallbacks'))
            ->getClosure($this->container);
        $fire($attributes, $sentinel);

        $this->assertNotNull($captured);
        $this->assertInstanceOf(TestContextualAttribute::class, $captured[0]);
        $this->assertSame('logger', $captured[0]->value);
        $this->assertSame($sentinel, $captured[1]);
        $this->assertSame($this->container, $captured[2]);
    }

    public function test_class_level_attribute_fires_once_on_first_resolve(): void
    {
        $count = 0;
        $this->container->afterResolvingAttribute(
            TestContextualAttribute::class,
            function ($attribute, $object, $container) use (&$count) {
                $count++;
            }
        );

        $this->container->singleton(StubWithTestAttribute::class);

        $this->container->make(StubWithTestAttribute::class);
        $this->container->make(StubWithTestAttribute::class);
        $this->container->make(StubWithTestAttribute::class);

        $this->assertSame(1, $count);
    }

    public function test_class_level_attribute_fires_each_resolve_for_non_shared(): void
    {
        $count = 0;
        $this->container->afterResolvingAttribute(
            TestContextualAttribute::class,
            function ($attribute, $object, $container) use (&$count) {
                $count++;
            }
        );

        $this->container->bind(StubWithTestAttribute::class);

        $this->container->make(StubWithTestAttribute::class);
        $this->container->make(StubWithTestAttribute::class);

        $this->assertSame(2, $count);
    }

    public function test_class_level_attribute_callback_throw_propagates(): void
    {
        $this->container->afterResolvingAttribute(
            TestContextualAttribute::class,
            function () {
                throw new \LogicException('boom');
            }
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('boom');

        $this->container->make(StubWithTestAttribute::class);
    }

    public function test_param_resolution_fires_after_resolving_attribute_callback(): void
    {
        $captured = [];
        $this->container->afterResolvingAttribute(
            TestContextualAttribute::class,
            function ($attribute, $object, $container) use (&$captured) {
                $captured[] = [$attribute->value, $object];
            }
        );

        $instance = $this->container->make(StubConsumer::class);

        $this->assertCount(1, $captured);
        $this->assertSame('hello', $captured[0][0]);
        $this->assertSame('hello', $captured[0][1]);
        $this->assertSame('hello', $instance->value);
    }

    public function test_fire_skips_non_contextual_attributes(): void
    {
        $invoked = false;
        $this->container->afterResolvingAttribute(
            \Attribute::class,
            function () use (&$invoked) {
                $invoked = true;
            }
        );

        $reflection = new \ReflectionClass(StubWithPlainAttribute::class);
        $fire = (new \ReflectionMethod($this->container, 'fireAfterResolvingAttributeCallbacks'))
            ->getClosure($this->container);
        $fire($reflection->getAttributes(), new \stdClass());

        $this->assertFalse($invoked);
    }
}

#[TestContextualAttribute('logger')]
class StubWithTestAttribute {}

#[\Attribute]
class StubWithPlainAttribute {}

class StubConsumer
{
    public function __construct(
        #[TestContextualAttribute('hello')] public string $value
    ) {}
}

#[\Attribute(\Attribute::TARGET_ALL)]
final class TestContextualAttribute implements ContextualAttributeContract
{
    public function __construct(public string $value) {}

    public static function resolve(self $attribute, Container $container): string
    {
        return $attribute->value;
    }
}
