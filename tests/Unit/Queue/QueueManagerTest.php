<?php

use Phare\Queue\QueueManager;
use Phare\Queue\Job;
use Phare\Queue\SyncQueue;
use Phare\Queue\DatabaseQueue;
use Phare\Queue\RedisQueue;

// Test job for queue manager
class QueueTestJob extends Job
{
    public bool $handled = false;
    public ?\Exception $exception = null;

    public function __construct(public string $message = 'Queue test')
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->handled = true;
    }

    public function failed(\Exception $exception): void
    {
        $this->exception = $exception;
    }
}

class FailingQueueJob extends Job
{
    public function __construct(public int $failAfter = 0)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        if ($this->getRetries() >= $this->failAfter) {
            throw new \Exception('Job failed after ' . $this->getRetries() . ' retries');
        }
    }
}

beforeEach(function () {
    $this->config = [
        'default' => 'sync',
        'connections' => [
            'sync' => ['driver' => 'sync'],
            'database' => ['driver' => 'database', 'table' => 'jobs'],
            'redis' => ['driver' => 'redis'],
        ]
    ];

    $this->manager = new QueueManager($this->config);
});

test('queue manager can be instantiated', function () {
    expect($this->manager)->toBeInstanceOf(QueueManager::class);
});

test('queue manager has default connection', function () {
    expect($this->manager->getDefaultConnection())->toBe('sync');
});

test('queue manager can set default connection', function () {
    $this->manager->setDefaultConnection('database');
    expect($this->manager->getDefaultConnection())->toBe('database');
});

test('queue manager can get sync connection', function () {
    $connection = $this->manager->connection('sync');
    expect($connection)->toBeInstanceOf(SyncQueue::class);
});

test('queue manager can get database connection', function () {
    $connection = $this->manager->connection('database');
    expect($connection)->toBeInstanceOf(DatabaseQueue::class);
});

test('queue manager can get redis connection', function () {
    $connection = $this->manager->connection('redis');
    expect($connection)->toBeInstanceOf(RedisQueue::class);
});

test('queue manager returns default connection when none specified', function () {
    $connection = $this->manager->connection();
    expect($connection)->toBeInstanceOf(SyncQueue::class);
});

test('queue manager can push job', function () {
    $job = new QueueTestJob('Push test');
    $jobId = $this->manager->push($job);
    
    expect($jobId)->toBeString();
    expect($job->handled)->toBeTrue(); // Sync queue executes immediately
});

test('queue manager can push job with delay', function () {
    $job = new QueueTestJob('Delayed test');
    $jobId = $this->manager->later($job, 60);
    
    expect($jobId)->toBeString();
    expect($job->getDelay())->toBe(60);
});

test('queue manager can push job to specific queue', function () {
    $job = new QueueTestJob('Queue test');
    $jobId = $this->manager->push($job, 'high-priority');
    
    expect($jobId)->toBeString();
    expect($job->getQueue())->toBe('high-priority');
});

test('queue manager can push job to specific connection', function () {
    $job = new QueueTestJob('Connection test');
    $jobId = $this->manager->push($job, null, 'database');
    
    expect($jobId)->toBeString();
});

test('queue manager can get queue size', function () {
    // Using database connection for size testing
    $size = $this->manager->size(null, 'database');
    expect($size)->toBeInt();
    expect($size)->toBe(0); // Initially empty
});

test('queue manager can clear queue', function () {
    $cleared = $this->manager->clear(null, 'database');
    expect($cleared)->toBeInt();
    expect($cleared)->toBe(0); // Nothing to clear initially
});

test('queue manager can extend with custom driver', function () {
    $this->manager->extend('custom', function ($config) {
        return new class implements \Phare\Queue\Connectors\ConnectorInterface {
            public function connect(array $config): \Phare\Queue\QueueInterface
            {
                return new class implements \Phare\Queue\QueueInterface {
                    public function push(Job $job, ?string $queue = null): string { return 'custom-id'; }
                    public function pop(?string $queue = null): ?Job { return null; }
                    public function size(?string $queue = null): int { return 0; }
                    public function clear(?string $queue = null): int { return 0; }
                    public function delete(Job $job): bool { return true; }
                };
            }
        };
    });

    // Add custom connection to config
    $manager = new QueueManager([
        'default' => 'custom',
        'connections' => [
            'custom' => ['driver' => 'custom']
        ]
    ]);

    $connection = $manager->connection('custom');
    expect($connection)->toBeInstanceOf(\Phare\Queue\QueueInterface::class);
});

test('queue manager throws exception for unknown driver', function () {
    $manager = new QueueManager([
        'default' => 'unknown',
        'connections' => [
            'unknown' => ['driver' => 'unknown']
        ]
    ]);

    expect(function () use ($manager) {
        $manager->connection('unknown');
    })->toThrow(\InvalidArgumentException::class, 'No connector for [unknown]');
});

test('queue manager can get all connections', function () {
    // Create some connections
    $this->manager->connection('sync');
    $this->manager->connection('database');
    
    $connections = $this->manager->getConnections();
    expect($connections)->toBeArray();
    expect($connections)->toHaveKey('sync');
    expect($connections)->toHaveKey('database');
});

test('queue manager can get config', function () {
    $config = $this->manager->getConfig();
    expect($config)->toBe($this->config);
});

test('queue manager can pop jobs', function () {
    // Test with database connection since sync doesn't store jobs
    $job = $this->manager->pop(null, 'database');
    expect($job)->toBeNull(); // No jobs initially
});

test('queue manager process job method works', function () {
    // We'll test this indirectly through the work method in integration tests
    expect(method_exists($this->manager, 'work'))->toBeTrue();
});

test('queue manager handles job failures correctly', function () {
    // Use reflection to test protected method
    $reflection = new ReflectionClass($this->manager);
    $method = $reflection->getMethod('handleFailedJob');
    $method->setAccessible(true);

    $job = new FailingQueueJob(0); // Fail immediately
    $exception = new \Exception('Test failure');

    $method->invoke($this->manager, $job, $exception);
    
    expect($job->getRetries())->toBe(1);
});

test('queue manager can work with max jobs limit', function () {
    // This is a basic test - full testing would require integration
    expect(method_exists($this->manager, 'work'))->toBeTrue();
    
    // Verify method signature by creating a basic mock
    $reflection = new ReflectionClass($this->manager);
    $method = $reflection->getMethod('work');
    $parameters = $method->getParameters();
    
    expect($parameters)->toHaveCount(3);
    expect($parameters[0]->getName())->toBe('queue');
    expect($parameters[1]->getName())->toBe('connection');
    expect($parameters[2]->getName())->toBe('maxJobs');
});

test('queue manager reuses connection instances', function () {
    $connection1 = $this->manager->connection('sync');
    $connection2 = $this->manager->connection('sync');
    
    expect($connection1)->toBe($connection2); // Same instance
});

test('queue manager different connections are different instances', function () {
    $syncConnection = $this->manager->connection('sync');
    $databaseConnection = $this->manager->connection('database');
    
    expect($syncConnection)->not->toBe($databaseConnection);
});