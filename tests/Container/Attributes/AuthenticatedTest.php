<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Auth\AuthenticationException;
use Phare\Container\Attributes\Authenticated;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class AuthenticatedTest extends TestCase
{
    public function test_returns_user_when_authenticated(): void
    {
        $container = new Container();
        $manager = new FakeAuthManager();
        $manager->userInstance = (object)['id' => 7];
        $container->singleton('auth', fn () => $manager);

        $consumer = $container->make(AuthenticatedStubConsumer::class);

        $this->assertSame($manager->userInstance, $consumer->user);
    }

    public function test_throws_authentication_exception_when_no_user(): void
    {
        $container = new Container();
        $container->singleton('auth', fn () => new FakeAuthManager());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthenticated.');

        $container->make(AuthenticatedStubConsumer::class);
    }

    public function test_resolves_user_via_named_guard(): void
    {
        $container = new Container();

        $apiGuard = new FakeAuthManager();
        $apiGuard->userInstance = (object)['id' => 99];

        $managerSpy = new class($apiGuard)
        {
            public function __construct(private FakeAuthManager $api) {}

            public function guard(?string $name = null): FakeAuthManager
            {
                return $this->api;
            }
        };

        $container->singleton('auth.manager', fn () => $managerSpy);
        $container->singleton('auth', fn () => new FakeAuthManager());

        $consumer = $container->make(AuthenticatedApiStubConsumer::class);

        $this->assertSame($apiGuard->userInstance, $consumer->user);
    }

    public function test_named_guard_throws_when_user_missing(): void
    {
        $container = new Container();

        $managerSpy = new class()
        {
            public function guard(?string $name = null): FakeAuthManager
            {
                return new FakeAuthManager();
            }
        };

        $container->singleton('auth.manager', fn () => $managerSpy);
        $container->singleton('auth', fn () => new FakeAuthManager());

        $this->expectException(AuthenticationException::class);

        $container->make(AuthenticatedApiStubConsumer::class);
    }
}

class AuthenticatedStubConsumer
{
    public function __construct(#[Authenticated] public object $user) {}
}

class AuthenticatedApiStubConsumer
{
    public function __construct(#[Authenticated('api')] public object $user) {}
}
