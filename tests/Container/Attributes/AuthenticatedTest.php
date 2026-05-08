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
}

class AuthenticatedStubConsumer
{
    public function __construct(#[Authenticated] public object $user) {}
}
