<?php

use Phare\Queue\Job;
use Phare\Queue\Queueable;

// Test job classes
class SimpleTestJob extends Job
{
    use Queueable;

    public bool $handled = false;
    public ?\Exception $failException = null;

    public function __construct(
        public string $message = 'Test message',
        public bool $shouldFail = false
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new \Exception('Job intentionally failed');
        }

        $this->handled = true;
    }

    public function failed(\Exception $exception): void
    {
        $this->failException = $exception;
    }
}

class DelayedTestJob extends Job
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}

class CustomQueueJob extends Job
{
    public function __construct()
    {
        parent::__construct();
        $this->onQueue('custom')->timeout(120)->retries(5);
    }

    public function handle(): void
    {
        // Test job
    }
}

test('job can be instantiated', function () {
    $job = new SimpleTestJob('Hello World');
    
    expect($job)->toBeInstanceOf(Job::class);
    expect($job->message)->toBe('Hello World');
    expect($job->getJobId())->toMatch('/^job_/');
    expect($job->getCreatedAt())->toBeInstanceOf(\DateTime::class);
});

test('job has default values', function () {
    $job = new SimpleTestJob();
    
    expect($job->getQueue())->toBe('default');
    expect($job->getDelay())->toBe(0);
    expect($job->getTimeout())->toBe(60);
    expect($job->getRetries())->toBe(0);
    expect($job->getMaxRetries())->toBe(3);
    expect($job->getData())->toBe([]);
    expect($job->getAvailableAt())->toBeNull();
});

test('job can set queue', function () {
    $job = (new SimpleTestJob())->onQueue('high-priority');
    
    expect($job->getQueue())->toBe('high-priority');
});

test('job can set delay', function () {
    $job = (new SimpleTestJob())->delay(300);
    
    expect($job->getDelay())->toBe(300);
    expect($job->getAvailableAt())->toBeInstanceOf(\DateTime::class);
});

test('job can set timeout', function () {
    $job = (new SimpleTestJob())->timeout(120);
    
    expect($job->getTimeout())->toBe(120);
});

test('job can set max retries', function () {
    $job = (new SimpleTestJob())->retries(5);
    
    expect($job->getMaxRetries())->toBe(5);
});

test('job can add data', function () {
    $job = (new SimpleTestJob())->withData(['key' => 'value', 'number' => 123]);
    
    expect($job->getData())->toBe(['key' => 'value', 'number' => 123]);
});

test('job can chain configuration methods', function () {
    $job = (new SimpleTestJob())
        ->onQueue('high')
        ->delay(60)
        ->timeout(300)
        ->retries(5)
        ->withData(['test' => true]);
    
    expect($job->getQueue())->toBe('high');
    expect($job->getDelay())->toBe(60);
    expect($job->getTimeout())->toBe(300);
    expect($job->getMaxRetries())->toBe(5);
    expect($job->getData())->toBe(['test' => true]);
});

test('job can be handled successfully', function () {
    $job = new SimpleTestJob('Test');
    $job->handle();
    
    expect($job->handled)->toBeTrue();
});

test('job can fail and call failed handler', function () {
    $job = new SimpleTestJob('Test', true);
    
    try {
        $job->handle();
    } catch (\Exception $e) {
        $job->failed($e);
    }
    
    expect($job->handled)->toBeFalse();
    expect($job->failException)->toBeInstanceOf(\Exception::class);
    expect($job->failException->getMessage())->toBe('Job intentionally failed');
});

test('job can increment retry count', function () {
    $job = new SimpleTestJob();
    
    expect($job->getRetries())->toBe(0);
    $job->incrementRetries();
    expect($job->getRetries())->toBe(1);
    $job->incrementRetries();
    expect($job->getRetries())->toBe(2);
});

test('job can check if it can be retried', function () {
    $job = (new SimpleTestJob())->retries(2);
    
    expect($job->canRetry())->toBeTrue();
    
    $job->incrementRetries();
    expect($job->canRetry())->toBeTrue();
    
    $job->incrementRetries();
    expect($job->canRetry())->toBeFalse();
});

test('job is available immediately by default', function () {
    $job = new SimpleTestJob();
    
    expect($job->isAvailable())->toBeTrue();
});

test('job with delay is not available immediately', function () {
    $job = (new SimpleTestJob())->delay(60);
    
    expect($job->isAvailable())->toBeFalse();
});

test('job can be serialized', function () {
    $job = (new SimpleTestJob('Serialize Test'))
        ->onQueue('test')
        ->delay(60)
        ->timeout(120)
        ->retries(2)
        ->withData(['test' => true]);
    
    $serialized = $job->serialize();
    
    expect($serialized)->toBeArray();
    expect($serialized['job_id'])->toBe($job->getJobId());
    expect($serialized['class'])->toBe(SimpleTestJob::class);
    expect($serialized['queue'])->toBe('test');
    expect($serialized['delay'])->toBe(60);
    expect($serialized['timeout'])->toBe(120);
    expect($serialized['max_retries'])->toBe(2);
    expect($serialized['data'])->toBe(['test' => true]);
});

test('job can be deserialized', function () {
    $originalJob = (new SimpleTestJob('Deserialize Test'))
        ->onQueue('test')
        ->delay(60)
        ->timeout(120)
        ->retries(2)
        ->withData(['test' => true]);
    
    $serialized = $originalJob->serialize();
    $deserializedJob = SimpleTestJob::deserialize($serialized);
    
    expect($deserializedJob->getJobId())->toBe($originalJob->getJobId());
    expect($deserializedJob->getQueue())->toBe($originalJob->getQueue());
    expect($deserializedJob->getDelay())->toBe($originalJob->getDelay());
    expect($deserializedJob->getTimeout())->toBe($originalJob->getTimeout());
    expect($deserializedJob->getMaxRetries())->toBe($originalJob->getMaxRetries());
    expect($deserializedJob->getData())->toBe($originalJob->getData());
});

test('custom queue job has custom settings', function () {
    $job = new CustomQueueJob();
    
    expect($job->getQueue())->toBe('custom');
    expect($job->getTimeout())->toBe(120);
    expect($job->getMaxRetries())->toBe(5);
});

test('queueable trait dispatch method works', function () {
    // We can't fully test dispatch without mocking app() function
    // But we can test that the trait methods exist
    expect(method_exists(SimpleTestJob::class, 'dispatch'))->toBeTrue();
    expect(method_exists(SimpleTestJob::class, 'dispatchAfter'))->toBeTrue();
    expect(method_exists(SimpleTestJob::class, 'dispatchOn'))->toBeTrue();
    expect(method_exists(SimpleTestJob::class, 'dispatchUsing'))->toBeTrue();
});

test('job data can be merged multiple times', function () {
    $job = (new SimpleTestJob())
        ->withData(['key1' => 'value1'])
        ->withData(['key2' => 'value2', 'key1' => 'updated']);
    
    expect($job->getData())->toBe(['key1' => 'updated', 'key2' => 'value2']);
});

test('job available at is set correctly with delay', function () {
    $beforeTime = new \DateTime();
    $job = (new SimpleTestJob())->delay(300);
    $afterTime = new \DateTime('+300 seconds');
    
    $availableAt = $job->getAvailableAt();
    expect($availableAt)->toBeInstanceOf(\DateTime::class);
    expect($availableAt->getTimestamp())->toBeGreaterThanOrEqual($beforeTime->getTimestamp() + 300);
    expect($availableAt->getTimestamp())->toBeLessThanOrEqual($afterTime->getTimestamp());
});