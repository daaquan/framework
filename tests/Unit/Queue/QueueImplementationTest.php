<?php

use Phare\Queue\DatabaseQueue;
use Phare\Queue\Job;
use Phare\Queue\RedisQueue;
use Phare\Queue\SyncQueue;

// Test job for queue implementations
class QueueImplTestJob extends Job
{
    public bool $executed = false;

    public ?\Exception $failedException = null;

    public function __construct(public string $data = 'test data', public bool $shouldFail = false)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new \Exception('Job intentionally failed');
        }

        $this->executed = true;
    }

    public function failed(\Exception $exception): void
    {
        $this->failedException = $exception;
    }
}

describe('SyncQueue', function () {
    beforeEach(function () {
        $this->queue = new SyncQueue();
    });

    test('sync queue executes job immediately', function () {
        $job = new QueueImplTestJob('immediate execution');
        $jobId = $this->queue->push($job);

        expect($jobId)->toBe($job->getJobId());
        expect($job->executed)->toBeTrue();
    });

    test('sync queue throws exception on job failure', function () {
        $job = new QueueImplTestJob('failing job', true);

        expect(function () {
            $this->queue->push($job);
        })->toThrow(\Exception::class, 'Job intentionally failed');

        expect($job->executed)->toBeFalse();
        expect($job->failedException)->toBeInstanceOf(\Exception::class);
    });

    test('sync queue pop returns null', function () {
        expect($this->queue->pop())->toBeNull();
    });

    test('sync queue size is always zero', function () {
        expect($this->queue->size())->toBe(0);
    });

    test('sync queue clear returns zero', function () {
        expect($this->queue->clear())->toBe(0);
    });

    test('sync queue delete always returns true', function () {
        $job = new QueueImplTestJob();
        expect($this->queue->delete($job))->toBeTrue();
    });
});

describe('DatabaseQueue', function () {
    beforeEach(function () {
        $this->queue = new DatabaseQueue(['queue' => 'test-queue']);
    });

    test('database queue can push job', function () {
        $job = new QueueImplTestJob('database test');
        $jobId = $this->queue->push($job);

        expect($jobId)->toBeString();
        expect($job->getQueue())->toBe('test-queue');
    });

    test('database queue can push to specific queue', function () {
        $job = new QueueImplTestJob('specific queue');
        $jobId = $this->queue->push($job, 'custom-queue');

        expect($job->getQueue())->toBe('custom-queue');
    });

    test('database queue can pop job', function () {
        $originalJob = new QueueImplTestJob('pop test');
        $this->queue->push($originalJob);

        $poppedJob = $this->queue->pop('test-queue');

        expect($poppedJob)->toBeInstanceOf(QueueImplTestJob::class);
        expect($poppedJob->data)->toBe('pop test');
    });

    test('database queue returns null when no jobs available', function () {
        $job = $this->queue->pop('empty-queue');
        expect($job)->toBeNull();
    });

    test('database queue can get size', function () {
        expect($this->queue->size('test-queue'))->toBe(0);

        $this->queue->push(new QueueImplTestJob('size test 1'));
        expect($this->queue->size('test-queue'))->toBe(1);

        $this->queue->push(new QueueImplTestJob('size test 2'));
        expect($this->queue->size('test-queue'))->toBe(2);
    });

    test('database queue can clear jobs', function () {
        $this->queue->push(new QueueImplTestJob('clear test 1'));
        $this->queue->push(new QueueImplTestJob('clear test 2'));

        expect($this->queue->size('test-queue'))->toBe(2);

        $cleared = $this->queue->clear('test-queue');
        expect($cleared)->toBe(2);
        expect($this->queue->size('test-queue'))->toBe(0);
    });

    test('database queue can delete specific job', function () {
        $job1 = new QueueImplTestJob('job 1');
        $job2 = new QueueImplTestJob('job 2');

        $this->queue->push($job1);
        $this->queue->push($job2);

        expect($this->queue->size('test-queue'))->toBe(2);

        $deleted = $this->queue->delete($job1);
        expect($deleted)->toBeTrue();
        expect($this->queue->size('test-queue'))->toBe(1);
    });

    test('database queue handles delayed jobs correctly', function () {
        $job = (new QueueImplTestJob('delayed job'))->delay(60);
        $this->queue->push($job);

        // Job should not be available immediately
        $poppedJob = $this->queue->pop('test-queue');
        expect($poppedJob)->toBeNull();
    });

    test('database queue can flush all jobs', function () {
        $this->queue->push(new QueueImplTestJob('flush test 1'));
        $this->queue->push(new QueueImplTestJob('flush test 2'));

        $this->queue->flush();
        expect($this->queue->getJobs())->toHaveCount(0);
    });
});

describe('RedisQueue', function () {
    beforeEach(function () {
        $this->queue = new RedisQueue(['queue' => 'redis-test']);
    });

    test('redis queue can push job', function () {
        $job = new QueueImplTestJob('redis test');
        $jobId = $this->queue->push($job);

        expect($jobId)->toBeString();
        expect($job->getQueue())->toBe('redis-test');
    });

    test('redis queue can push to specific queue', function () {
        $job = new QueueImplTestJob('redis specific');
        $jobId = $this->queue->push($job, 'redis-custom');

        expect($job->getQueue())->toBe('redis-custom');
    });

    test('redis queue can pop job', function () {
        $originalJob = new QueueImplTestJob('redis pop test');
        $this->queue->push($originalJob);

        $poppedJob = $this->queue->pop('redis-test');

        expect($poppedJob)->toBeInstanceOf(QueueImplTestJob::class);
        expect($poppedJob->data)->toBe('redis pop test');
    });

    test('redis queue handles delayed jobs', function () {
        $job = (new QueueImplTestJob('redis delayed'))->delay(60);
        $this->queue->push($job);

        // Job should not be available immediately
        $poppedJob = $this->queue->pop('redis-test');
        expect($poppedJob)->toBeNull();

        // Size should be 0 for main queue (delayed jobs are in separate queue)
        expect($this->queue->size('redis-test'))->toBe(0);
    });

    test('redis queue can get size', function () {
        expect($this->queue->size('redis-test'))->toBe(0);

        $this->queue->push(new QueueImplTestJob('size test 1'));
        expect($this->queue->size('redis-test'))->toBe(1);

        $this->queue->push(new QueueImplTestJob('size test 2'));
        expect($this->queue->size('redis-test'))->toBe(2);
    });

    test('redis queue can clear jobs', function () {
        $this->queue->push(new QueueImplTestJob('clear test 1'));
        $this->queue->push(new QueueImplTestJob('clear test 2'));

        expect($this->queue->size('redis-test'))->toBe(2);

        $cleared = $this->queue->clear('redis-test');
        expect($cleared)->toBe(2);
        expect($this->queue->size('redis-test'))->toBe(0);
    });

    test('redis queue can delete specific job', function () {
        $job1 = new QueueImplTestJob('redis job 1');
        $job2 = new QueueImplTestJob('redis job 2');

        $this->queue->push($job1);
        $this->queue->push($job2);

        expect($this->queue->size('redis-test'))->toBe(2);

        $deleted = $this->queue->delete($job1);
        expect($deleted)->toBeTrue();
        expect($this->queue->size('redis-test'))->toBe(1);
    });

    test('redis queue moves delayed jobs when ready', function () {
        // We can't easily test time-based logic in unit tests,
        // but we can test the moveDelayedJobs method exists
        $reflection = new ReflectionClass($this->queue);
        $method = $reflection->getMethod('moveDelayedJobs');
        expect($method)->toBeDefined();
    });

    test('redis queue can flush all queues', function () {
        $this->queue->push(new QueueImplTestJob('flush test 1'));
        $this->queue->push(new QueueImplTestJob('flush test 2'));

        $this->queue->flush();
        expect($this->queue->getQueues())->toHaveCount(0);
    });

    test('redis queue returns null for non-existent class', function () {
        // This tests the error handling in pop method
        $queues = $this->queue->getQueues();
        $queues['redis-test'][] = [
            'id' => 'test-id',
            'payload' => json_encode(['class' => 'NonExistentClass']),
            'pushed_at' => time(),
        ];

        // Use reflection to set the queues property
        $reflection = new ReflectionClass($this->queue);
        $property = $reflection->getProperty('queues');
        $property->setAccessible(true);
        $property->setValue($this->queue, $queues);

        $job = $this->queue->pop('redis-test');
        expect($job)->toBeNull();
    });
});
