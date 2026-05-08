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
