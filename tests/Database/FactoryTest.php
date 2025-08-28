<?php

use Phare\Database\Factory;
use Phare\Database\BaseFactory;
use Phare\Database\Schema\Blueprint;
use Tests\TestCase;

class FactoryTest extends TestCase
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
                $table->integer('age');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        $connection = $this->app->make('db');
        $connection->execute("DELETE FROM test_users");
        
        parent::tearDown();
    }
}

it('can create factory instance', function () {
    $factory = new Factory($this->app);
    
    expect($factory)->toBeInstanceOf(Factory::class);
})->uses(FactoryTest::class);

it('can set model for factory', function () {
    $factory = new Factory($this->app);
    $factory->for('User');
    
    expect($factory)->toBeInstanceOf(Factory::class);
})->uses(FactoryTest::class);

it('can set count for factory', function () {
    $factory = new Factory($this->app);
    $factory->count(5);
    
    expect($factory)->toBeInstanceOf(Factory::class);
})->uses(FactoryTest::class);

it('can set state for factory', function () {
    $factory = new Factory($this->app);
    $factory->state(['active' => true, 'verified' => true]);
    
    expect($factory)->toBeInstanceOf(Factory::class);
})->uses(FactoryTest::class);

it('can chain factory methods', function () {
    $factory = new Factory($this->app);
    $result = $factory->for('User')->count(3)->state(['active' => true]);
    
    expect($result)->toBeInstanceOf(Factory::class);
})->uses(FactoryTest::class);

// Create a test factory for testing
class TestUserFactory extends BaseFactory
{
    public function definition(): array
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 25,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
}

it('can make single instance without persisting', function () {
    // Mock the factory class resolution
    $factory = new class($this->app) extends Factory {
        protected function getDefinition(): array
        {
            return [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'age' => 25,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        
        protected function getTableName(): string
        {
            return 'test_users';
        }
    };
    
    $instance = $factory->for('TestUser')->make();
    
    expect($instance)->toBeArray();
    expect($instance['name'])->toBe('Test User');
    expect($instance['email'])->toBe('test@example.com');
    expect($instance['age'])->toBe(25);
})->uses(FactoryTest::class);

it('can make multiple instances without persisting', function () {
    // Mock the factory class resolution
    $factory = new class($this->app) extends Factory {
        protected function getDefinition(): array
        {
            return [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'age' => 25,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        
        protected function getTableName(): string
        {
            return 'test_users';
        }
    };
    
    $instances = $factory->for('TestUser')->count(3)->make();
    
    expect($instances)->toBeArray();
    expect($instances)->toHaveCount(3);
    expect($instances[0]['name'])->toBe('Test User');
    expect($instances[1]['name'])->toBe('Test User');
    expect($instances[2]['name'])->toBe('Test User');
})->uses(FactoryTest::class);

it('can create and persist single instance', function () {
    // Mock the factory class resolution
    $factory = new class($this->app) extends Factory {
        protected function getDefinition(): array
        {
            return [
                'name' => 'Persisted User',
                'email' => 'persisted@example.com',
                'age' => 30,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        
        protected function getTableName(): string
        {
            return 'test_users';
        }
    };
    
    $instance = $factory->for('TestUser')->create();
    
    expect($instance)->toBeArray();
    expect($instance['name'])->toBe('Persisted User');
    
    // Verify it was persisted to database
    $connection = $this->app->make('db');
    $result = $connection->fetchOne("SELECT COUNT(*) as count FROM test_users WHERE name = ?", ['Persisted User']);
    expect($result['count'])->toBe(1);
})->uses(FactoryTest::class);

it('can create and persist multiple instances', function () {
    // Mock the factory class resolution
    $factory = new class($this->app) extends Factory {
        private int $counter = 0;
        
        protected function getDefinition(): array
        {
            $this->counter++;
            return [
                'name' => "Multi User {$this->counter}",
                'email' => "multi{$this->counter}@example.com",
                'age' => 20 + $this->counter,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        
        protected function getTableName(): string
        {
            return 'test_users';
        }
    };
    
    $instances = $factory->for('TestUser')->count(3)->create();
    
    expect($instances)->toBeArray();
    expect($instances)->toHaveCount(3);
    
    // Verify they were persisted to database
    $connection = $this->app->make('db');
    $result = $connection->fetchOne("SELECT COUNT(*) as count FROM test_users WHERE name LIKE ?", ['Multi User%']);
    expect($result['count'])->toBe(3);
})->uses(FactoryTest::class);

it('can override attributes when making instances', function () {
    // Mock the factory class resolution
    $factory = new class($this->app) extends Factory {
        protected function getDefinition(): array
        {
            return [
                'name' => 'Default User',
                'email' => 'default@example.com',
                'age' => 25,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        
        protected function getTableName(): string
        {
            return 'test_users';
        }
    };
    
    $instance = $factory->for('TestUser')->make(['name' => 'Override User', 'age' => 35]);
    
    expect($instance['name'])->toBe('Override User');
    expect($instance['age'])->toBe(35);
    expect($instance['email'])->toBe('default@example.com'); // Should keep default
})->uses(FactoryTest::class);

it('can merge states with overrides', function () {
    // Mock the factory class resolution
    $factory = new class($this->app) extends Factory {
        protected function getDefinition(): array
        {
            return [
                'name' => 'Base User',
                'email' => 'base@example.com',
                'age' => 25,
                'is_active' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        
        protected function getTableName(): string
        {
            return 'test_users';
        }
    };
    
    $instance = $factory->for('TestUser')
        ->state(['is_active' => true, 'age' => 30])
        ->make(['name' => 'Final User']);
    
    expect($instance['name'])->toBe('Final User');     // Override wins
    expect($instance['age'])->toBe(30);               // State wins over default
    expect($instance['is_active'])->toBe(true);       // State wins over default
    expect($instance['email'])->toBe('base@example.com'); // Default remains
})->uses(FactoryTest::class);