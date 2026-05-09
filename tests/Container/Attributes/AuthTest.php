<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Auth;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function test_resolves_auth_manager_from_container(): void
    {
        $container = new Container();
        $manager = new FakeAuthManager();
        $container->singleton('auth', fn () => $manager);

        $consumer = $container->make(AuthStubConsumer::class);

        $this->assertSame($manager, $consumer->auth);
    }

    public function test_resolves_named_guard_via_auth_manager(): void
    {
        $container = new Container();
        $defaultGuard = new FakeAuthManager();
        $apiGuard = new FakeAuthManager();

        $managerSpy = new class($defaultGuard, $apiGuard)
        {
            public ?string $lastRequested = null;

            public function __construct(private object $default, private object $api) {}

            public function guard(?string $name = null): object
            {
                $this->lastRequested = $name;

                return $name === 'api' ? $this->api : $this->default;
            }
        };

        $container->singleton('auth.manager', fn () => $managerSpy);
        $container->singleton('auth', fn () => $defaultGuard);

        $consumer = $container->make(AuthApiStubConsumer::class);

        $this->assertSame($apiGuard, $consumer->auth);
        $this->assertSame('api', $managerSpy->lastRequested);
    }
}

class AuthStubConsumer
{
    public function __construct(#[Auth] public mixed $auth) {}
}

class AuthApiStubConsumer
{
    public function __construct(#[Auth('api')] public mixed $auth) {}
}
