<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\CurrentUser;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class CurrentUserTest extends TestCase
{
    public function test_returns_current_user_from_auth_manager(): void
    {
        $container = new Container();
        $manager = new FakeAuthManager();
        $manager->userInstance = (object)['id' => 99];
        $container->singleton('auth', fn () => $manager);

        $consumer = $container->make(CurrentUserStubConsumer::class);

        $this->assertSame($manager->userInstance, $consumer->user);
    }

    public function test_returns_null_when_no_user(): void
    {
        $container = new Container();
        $container->singleton('auth', fn () => new FakeAuthManager());

        $consumer = $container->make(CurrentUserStubConsumer::class);

        $this->assertNull($consumer->user);
    }

    public function test_resolves_user_from_named_guard(): void
    {
        $container = new Container();

        $apiGuard = new FakeAuthManager();
        $apiGuard->userInstance = (object)['id' => 'api-user'];

        $managerSpy = new class($apiGuard)
        {
            public ?string $lastRequested = null;

            public function __construct(private FakeAuthManager $api) {}

            public function guard(?string $name = null): FakeAuthManager
            {
                $this->lastRequested = $name;

                return $this->api;
            }
        };

        $container->singleton('auth.manager', fn () => $managerSpy);
        $container->singleton('auth', fn () => new FakeAuthManager());

        $consumer = $container->make(CurrentUserApiStubConsumer::class);

        $this->assertSame($apiGuard->userInstance, $consumer->user);
        $this->assertSame('api', $managerSpy->lastRequested);
    }
}

class CurrentUserStubConsumer
{
    public function __construct(#[CurrentUser] public ?object $user = null) {}
}

class CurrentUserApiStubConsumer
{
    public function __construct(#[CurrentUser('api')] public ?object $user = null) {}
}
