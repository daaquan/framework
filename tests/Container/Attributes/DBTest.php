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
        $defaultConn = (object)['name' => 'default-conn'];
        $container->singleton('db', fn () => $defaultConn);

        $consumer = $container->make(DBDefaultStubConsumer::class);

        $this->assertSame($defaultConn, $consumer->db);
    }

    public function test_resolves_named_connection_via_db_manager(): void
    {
        $container = new Container();
        $manager = new FakeDatabaseManager();

        $container->singleton('db.manager', fn () => $manager);
        $container->singleton('db', fn () => (object)['name' => 'default-conn']);

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
