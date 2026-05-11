<?php

declare(strict_types=1);

use Phalcon\Di\Di;
use Phare\Filesystem\FilesystemManager;
use Phare\Filesystem\LocalFilesystem;
use Phare\Filesystem\NullFilesystem;
use Phare\Foundation\Bootstrap\HandleExceptions;
use Phare\Foundation\Bootstrap\LoadConfiguration;
use Phare\Foundation\Bootstrap\LoadEnvironmentVariables;
use Phare\Foundation\Bootstrap\RegisterFacades;
use Phare\Foundation\Bootstrap\RegisterProviders;

function bootFilesystemApplication(): void
{
    Di::reset();

    $_ENV['APP_BASE_PATH'] = 'tests/Mock';
    $app = require $_ENV['APP_BASE_PATH'] . '/bootstrap/app.php';
    $app->bootstrapWith([
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterProviders::class,
        RegisterFacades::class,
    ]);

    Di::setDefault($app);
}

beforeEach(function () {
    bootFilesystemApplication();
});

test('default disk uses local driver with configured root', function () {
    $tmp = sys_get_temp_dir() . '/phare-fs-' . uniqid();
    @mkdir($tmp, 0777, true);

    config([
        'filesystems.default' => 'local',
        'filesystems.disks' => [
            'local' => ['driver' => 'local', 'root' => $tmp],
        ],
    ]);

    $manager = new FilesystemManager();

    expect($manager->disk())->toBeInstanceOf(LocalFilesystem::class);
    expect($manager->disk()->getRoot())->toBe($tmp);

    @rmdir($tmp);
});

test('disk(name) returns named disk and caches it', function () {
    $tmp = sys_get_temp_dir() . '/phare-fs-' . uniqid();
    @mkdir($tmp, 0777, true);

    config([
        'filesystems.default' => 'local',
        'filesystems.disks' => [
            'local' => ['driver' => 'local', 'root' => $tmp],
            'fake' => ['driver' => 'null'],
        ],
    ]);

    $manager = new FilesystemManager();
    $a = $manager->disk('fake');
    $b = $manager->disk('fake');

    expect($a)->toBeInstanceOf(NullFilesystem::class);
    expect($a)->toBe($b);

    @rmdir($tmp);
});

test('driver() aliases disk() through support manager base', function () {
    config([
        'filesystems.default' => 'local',
        'filesystems.disks' => [
            'local' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
            'fake' => ['driver' => 'null'],
        ],
    ]);

    $manager = new FilesystemManager();

    expect($manager->driver('fake'))->toBe($manager->disk('fake'))
        ->and($manager->getDrivers())->toHaveKey('fake');
});

test('extend() registers a custom filesystem disk creator', function () {
    config([
        'filesystems.default' => 'local',
        'filesystems.disks' => [
            'local' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
        ],
    ]);

    $manager = new FilesystemManager();
    $disk = new NullFilesystem();

    $manager->extend('custom', fn () => $disk);

    expect($manager->disk('custom'))->toBe($disk);
});

test('forgetDrivers() clears cached filesystem disks', function () {
    config([
        'filesystems.default' => 'local',
        'filesystems.disks' => [
            'local' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
            'fake' => ['driver' => 'null'],
        ],
    ]);

    $manager = new FilesystemManager();
    $first = $manager->disk('fake');

    $manager->forgetDrivers();

    expect($manager->disk('fake'))->not->toBe($first);
});

test('local disk prefixes paths with root', function () {
    $tmp = sys_get_temp_dir() . '/phare-fs-' . uniqid();
    @mkdir($tmp, 0777, true);

    config([
        'filesystems.default' => 'local',
        'filesystems.disks' => [
            'local' => ['driver' => 'local', 'root' => $tmp],
        ],
    ]);

    $disk = (new FilesystemManager())->disk();

    expect($disk->put('hello.txt', 'hi'))->not->toBeFalse();
    expect($disk->get('hello.txt'))->toBe('hi');
    expect($disk->exists('hello.txt'))->toBeTrue();

    $disk->delete('hello.txt');
    @rmdir($tmp);
});

test('disk(unknown) throws when configuration is missing', function () {
    config([
        'filesystems.default' => 'local',
        'filesystems.disks' => [
            'local' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
        ],
    ]);

    $manager = new FilesystemManager();

    expect(fn () => $manager->disk('nope'))
        ->toThrow(InvalidArgumentException::class);
});

test('local driver throws when root path is missing', function () {
    config([
        'filesystems.default' => 'broken',
        'filesystems.disks' => [
            'broken' => ['driver' => 'local'],
        ],
    ]);

    $manager = new FilesystemManager();

    expect(fn () => $manager->disk('broken'))
        ->toThrow(InvalidArgumentException::class);
});

test('unsupported driver throws', function () {
    config([
        'filesystems.default' => 's3',
        'filesystems.disks' => [
            's3' => ['driver' => 's3'],
        ],
    ]);

    $manager = new FilesystemManager();

    expect(fn () => $manager->disk())
        ->toThrow(InvalidArgumentException::class);
});
