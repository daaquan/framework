<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Storage;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    public function test_resolves_filesystem_manager(): void
    {
        $container = new Container();
        $fs = new \stdClass();
        $container->singleton('filesystem', fn () => $fs);

        $consumer = $container->make(StorageStubConsumer::class);

        $this->assertSame($fs, $consumer->fs);
    }
}

class StorageStubConsumer
{
    public function __construct(#[Storage] public mixed $fs) {}
}
