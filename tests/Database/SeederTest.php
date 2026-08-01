<?php

use Phalcon\Db\Enum;
use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Phare\Database\Seeder;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    test('seeder integration tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for seeder integration tests.');
    });

    return;
}

// Use seeder_test_users (not test_users) to avoid conflicts with FactoryTest.
// The beforeEach/afterEach are scoped to this describe block.
describe('Seeder', function () {
    beforeEach(function () {
        $connection = $this->app->make('db');
        $schema = new SchemaBuilder($connection);

        // Always drop and recreate to avoid stale schema from other tests
        if ($schema->hasTable('seeder_test_users')) {
            $schema->drop('seeder_test_users');
        }
        $schema->create('seeder_test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    });

    afterEach(function () {
        $connection = $this->app->make('db');
        if ($connection->fetchOne("SELECT COUNT(*) as c FROM sqlite_master WHERE type='table' AND name='seeder_test_users'")['c'] > 0) {
            $connection->execute('DELETE FROM seeder_test_users');
        }
    });

    test('seeder can insert data', function () {
        $app = $this->app;
        $seeder = new class($app) extends Seeder
        {
            public function run(): void
            {
                $this->create('seeder_test_users', [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        };

        $seeder->run();

        $connection = $this->app->make('db');
        $result = $connection->fetchOne('SELECT COUNT(*) as count FROM seeder_test_users');
        expect((int)$result['count'])->toBe(1);

        $user = $connection->fetchOne(
            'SELECT * FROM seeder_test_users WHERE email = ?',
            Enum::FETCH_ASSOC,
            ['john@example.com']
        );
        expect($user['name'])->toBe('John Doe');
    });

    test('seeder can insert multiple records', function () {
        $app = $this->app;
        $seeder = new class($app) extends Seeder
        {
            public function run(): void
            {
                $this->create('seeder_test_users', [
                    [
                        'name' => 'User 1',
                        'email' => 'user1@example.com',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    [
                        'name' => 'User 2',
                        'email' => 'user2@example.com',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                ]);
            }
        };

        $seeder->run();

        $connection = $this->app->make('db');
        $result = $connection->fetchOne('SELECT COUNT(*) as count FROM seeder_test_users');
        expect((int)$result['count'])->toBe(2);
    });

    test('seeder table helper', function () {
        $app = $this->app;
        $seeder = new class($app) extends Seeder
        {
            public function run(): void
            {
                $this->table('seeder_test_users')->insert([
                    'name' => 'Table Helper User',
                    'email' => 'tablehelper@example.com',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        };

        $seeder->run();

        $connection = $this->app->make('db');
        $user = $connection->fetchOne(
            'SELECT * FROM seeder_test_users WHERE email = ?',
            Enum::FETCH_ASSOC,
            ['tablehelper@example.com']
        );

        expect($user)->not->toBeNull();
        expect($user['name'])->toBe('Table Helper User');
    });
});
