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
}

class CurrentUserStubConsumer
{
    public function __construct(#[CurrentUser] public ?object $user = null) {}
}
