<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Session;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    public function test_resolves_default_store_when_no_arg(): void
    {
        $container = new Container();
        $defaultSession = (object)['name' => 'default-session'];
        $container->singleton('session', fn () => $defaultSession);

        $consumer = $container->make(SessionDefaultStubConsumer::class);

        $this->assertSame($defaultSession, $consumer->session);
    }

    public function test_resolves_named_store_via_session_manager(): void
    {
        $container = new Container();
        $manager = new FakeSessionStoreManager();

        $container->singleton('session.manager', fn () => $manager);
        $container->singleton('session', fn () => new \stdClass());

        $consumer = $container->make(SessionNamedStubConsumer::class);

        $this->assertSame('admin-store', $consumer->session);
    }
}

class FakeSessionStoreManager
{
    public function store(?string $name = null): string
    {
        return $name === null ? 'default-store' : "$name-store";
    }
}

class SessionDefaultStubConsumer
{
    public function __construct(#[Session] public mixed $session) {}
}

class SessionNamedStubConsumer
{
    public function __construct(#[Session('admin')] public mixed $session) {}
}
