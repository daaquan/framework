<?php

use Phare\Database\Seeder;
use Phare\Database\Factory;
use Phare\Database\Schema\Blueprint;
use Tests\TestCase;

class SeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $connection = $this->app->make('db');
        $schema = new \Phare\Database\Schema\SchemaBuilder($connection);
        
        // Create test table
        if (!$schema->hasTable('test_users')) {
            $schema->create('test_users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->timestamps();
            });
        }
    }

    public function test_seeder_can_insert_data()
    {
        $seeder = new class($this->app) extends Seeder {
            public function run(): void
            {
                $this->create('test_users', [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        };

        $seeder->run();

        $connection = $this->app->make('db');
        $result = $connection->fetchOne("SELECT COUNT(*) as count FROM test_users");
        
        $this->assertEquals(1, $result['count']);
        
        $user = $connection->fetchOne("SELECT * FROM test_users WHERE email = ?", ['john@example.com']);
        $this->assertEquals('John Doe', $user['name']);
    }

    public function test_seeder_can_insert_multiple_records()
    {
        $seeder = new class($this->app) extends Seeder {
            public function run(): void
            {
                $this->create('test_users', [
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
                    ]
                ]);
            }
        };

        $seeder->run();

        $connection = $this->app->make('db');
        $result = $connection->fetchOne("SELECT COUNT(*) as count FROM test_users");
        
        $this->assertEquals(2, $result['count']);
    }

    public function test_seeder_table_helper()
    {
        $seeder = new class($this->app) extends Seeder {
            public function run(): void
            {
                $this->table('test_users')->insert([
                    'name' => 'Table Helper User',
                    'email' => 'tablehelper@example.com',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        };

        $seeder->run();

        $connection = $this->app->make('db');
        $user = $connection->fetchOne("SELECT * FROM test_users WHERE email = ?", ['tablehelper@example.com']);
        
        $this->assertNotNull($user);
        $this->assertEquals('Table Helper User', $user['name']);
    }

    protected function tearDown(): void
    {
        $connection = $this->app->make('db');
        $connection->execute("DELETE FROM test_users");
        
        parent::tearDown();
    }
}