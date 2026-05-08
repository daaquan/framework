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
}

class FakeAuthManager
{
    public ?object $userInstance = null;

    public function user(): ?object
    {
        return $this->userInstance;
    }
}

class AuthStubConsumer
{
    public function __construct(#[Auth] public mixed $auth) {}
}
