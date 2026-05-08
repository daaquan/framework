<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\DB;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class DBTest extends TestCase
{
    public function test_resolves_default_connection_when_no_arg(): void
    {
        $container = new Container();
        $manager = new FakeDatabaseManager();
        $container->singleton('db', fn () => $manager);

        $consumer = $container->make(DBDefaultStubConsumer::class);

        $this->assertSame('default-conn', $consumer->db);
    }

    public function test_resolves_named_connection(): void
    {
        $container = new Container();
        $manager = new FakeDatabaseManager();
        $container->singleton('db', fn () => $manager);

        $consumer = $container->make(DBNamedStubConsumer::class);

        $this->assertSame('reports-conn', $consumer->db);
    }
}

class FakeDatabaseManager
{
    public function connection(?string $name = null): string
    {
        return $name === null ? 'default-conn' : "$name-conn";
    }
}

class DBDefaultStubConsumer
{
    public function __construct(#[DB] public mixed $db) {}
}

class DBNamedStubConsumer
{
    public function __construct(#[DB('reports')] public mixed $db) {}
}
