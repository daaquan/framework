<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use Phare\Container\Attributes\Storage;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    public function test_resolves_default_filesystem(): void
    {
        $container = new Container();
        $fs = new \stdClass();
        $container->singleton('filesystem', fn () => $fs);

        $consumer = $container->make(StorageStubConsumer::class);

        $this->assertSame($fs, $consumer->fs);
    }

    public function test_resolves_named_disk_via_filesystem_manager(): void
    {
        $container = new Container();
        $defaultDisk = new \stdClass();
        $namedDisk = new \stdClass();

        $managerSpy = new class($defaultDisk, $namedDisk)
        {
            public ?string $lastRequested = null;

            public function __construct(private object $default, private object $public) {}

            public function disk(?string $name = null): object
            {
                $this->lastRequested = $name;

                return $name === 'public' ? $this->public : $this->default;
            }
        };

        $container->singleton('filesystem.manager', fn () => $managerSpy);
        $container->singleton('filesystem', fn () => $defaultDisk);

        $consumer = $container->make(StorageNamedStubConsumer::class);

        $this->assertSame($namedDisk, $consumer->fs);
        $this->assertSame('public', $managerSpy->lastRequested);
    }
}

class StorageStubConsumer
{
    public function __construct(#[Storage] public mixed $fs) {}
}

class StorageNamedStubConsumer
{
    public function __construct(#[Storage('public')] public mixed $fs) {}
}
