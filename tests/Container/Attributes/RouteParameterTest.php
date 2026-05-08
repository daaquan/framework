<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\RouteParameter;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class RouteParameterTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_resolves_named_route_param_from_container_binding(): void
    {
        $this->container->singleton('routeParams', fn () => ['user' => '7', 'slug' => 'hello']);

        $consumer = $this->container->make(RouteParamStubConsumer::class);

        $this->assertSame('7', $consumer->user);
        $this->assertSame('hello', $consumer->slug);
    }

    public function test_returns_null_when_key_missing(): void
    {
        $this->container->singleton('routeParams', fn () => ['user' => '7']);

        $consumer = $this->container->make(RouteParamStubConsumer::class);

        $this->assertSame('7', $consumer->user);
        $this->assertNull($consumer->slug);
    }

    public function test_returns_null_when_route_params_binding_missing(): void
    {
        $consumer = $this->container->make(RouteParamStubConsumer::class);

        $this->assertNull($consumer->user);
        $this->assertNull($consumer->slug);
    }
}

class RouteParamStubConsumer
{
    public function __construct(
        #[RouteParameter('user')] public ?string $user = null,
        #[RouteParameter('slug')] public ?string $slug = null
    ) {}
}
