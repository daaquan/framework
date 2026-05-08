<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Give;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class GiveTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_give_builds_concrete_with_params(): void
    {
        $instance = $this->container->make(GiveStubConsumer::class);

        $this->assertSame(42, $instance->target->id);
        $this->assertSame('alpha', $instance->target->label);
    }

    public function test_give_resolves_for_variadic_param_as_single_item_array(): void
    {
        $instance = $this->container->make(GiveStubVariadicConsumer::class);

        $this->assertCount(1, $instance->targets);
        $this->assertSame('beta', $instance->targets[0]->label);
    }
}

class GiveStubTarget
{
    public function __construct(public int $id, public string $label) {}
}

class GiveStubConsumer
{
    public function __construct(
        #[Give(GiveStubTarget::class, ['id' => 42, 'label' => 'alpha'])]
        public GiveStubTarget $target
    ) {}
}

class GiveStubVariadicConsumer
{
    /** @var array<int, GiveStubTarget> */
    public array $targets;

    public function __construct(
        #[Give(GiveStubTarget::class, ['id' => 1, 'label' => 'beta'])]
        GiveStubTarget ...$targets
    ) {
        $this->targets = $targets;
    }
}
