<?php

use Mockery as m;
use Phare\Contracts\Foundation\Application;
use Phare\Database\MySql\DatabaseManager;

class TestableDatabaseManager extends DatabaseManager
{
    public function resolveSqlitePathForTest(array $config): string
    {
        return $this->resolveSqlitePath($config);
    }
}

afterEach(function () {
    m::close();
});

it('builds sqlite paths inside the database directory for relative names', function () {
    $app = m::mock(Application::class);
    $app->shouldReceive('databasePath')
        ->once()
        ->with('testing.sqlite')
        ->andReturn('/tmp/database/testing.sqlite');

    $manager = new TestableDatabaseManager($app, []);
    $path = $manager->resolveSqlitePathForTest(['database' => 'testing']);

    expect($path)->toBe('/tmp/database/testing.sqlite');
});

it('respects sqlite file names that already include the extension', function () {
    $app = m::mock(Application::class);
    $app->shouldReceive('databasePath')
        ->once()
        ->with('example.sqlite')
        ->andReturn('/tmp/database/example.sqlite');

    $manager = new TestableDatabaseManager($app, []);
    $path = $manager->resolveSqlitePathForTest(['database' => 'example.sqlite']);

    expect($path)->toBe('/tmp/database/example.sqlite');
});

it('accepts absolute sqlite paths verbatim', function () {
    $absolutePath = '/var/tmp/custom.sqlite';

    $app = m::mock(Application::class);
    $app->shouldReceive('databasePath')->never();

    $manager = new TestableDatabaseManager($app, []);
    $path = $manager->resolveSqlitePathForTest(['database' => $absolutePath]);

    expect($path)->toBe($absolutePath);
});

it('supports in-memory sqlite databases without touching the filesystem', function () {
    $app = m::mock(Application::class);
    $app->shouldReceive('databasePath')->never();

    $manager = new TestableDatabaseManager($app, []);
    $path = $manager->resolveSqlitePathForTest(['database' => ':memory:']);

    expect($path)->toBe(':memory:');
});
