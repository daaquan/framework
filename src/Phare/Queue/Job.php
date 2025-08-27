<?php

namespace Phare\Queue;

abstract class Job
{
    protected string $jobId;
    protected string $queue = 'default';
    protected int $delay = 0;
    protected int $timeout = 60;
    protected int $retries = 0;
    protected int $maxRetries = 3;
    protected array $data = [];
    protected ?\DateTimeInterface $availableAt = null;
    protected ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->jobId = $this->generateJobId();
        $this->createdAt = new \DateTime();
    }

    /**
     * Execute the job.
     */
    abstract public function handle(): void;

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Generate a unique job ID.
     */
    protected function generateJobId(): string
    {
        return uniqid('job_', true);
    }

    /**
     * Set the job queue.
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set the job delay in seconds.
     */
    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        $this->availableAt = new \DateTime('+' . $seconds . ' seconds');
        return $this;
    }

    /**
     * Set the job timeout in seconds.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set the maximum number of retries.
     */
    public function retries(int $retries): static
    {
        $this->maxRetries = $retries;
        return $this;
    }

    /**
     * Set additional data for the job.
     */
    public function withData(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Get the job ID.
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Get the queue name.
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Get the job delay.
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * Get the job timeout.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get the current retry count.
     */
    public function getRetries(): int
    {
        return $this->retries;
    }

    /**
     * Get the maximum retry count.
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Get the job data.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the time when the job becomes available.
     */
    public function getAvailableAt(): ?\DateTimeInterface
    {
        return $this->availableAt;
    }

    /**
     * Get the time when the job was created.
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Increment the retry count.
     */
    public function incrementRetries(): void
    {
        $this->retries++;
    }

    /**
     * Check if the job can be retried.
     */
    public function canRetry(): bool
    {
        return $this->retries < $this->maxRetries;
    }

    /**
     * Check if the job is available for processing.
     */
    public function isAvailable(): bool
    {
        if ($this->availableAt === null) {
            return true;
        }

        return $this->availableAt <= new \DateTime();
    }

    /**
     * Serialize the job for storage.
     */
    public function serialize(): array
    {
        return [
            'job_id' => $this->jobId,
            'class' => get_class($this),
            'queue' => $this->queue,
            'delay' => $this->delay,
            'timeout' => $this->timeout,
            'retries' => $this->retries,
            'max_retries' => $this->maxRetries,
            'data' => $this->data,
            'available_at' => $this->availableAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Deserialize a job from storage.
     */
    public static function deserialize(array $data): static
    {
        $job = new static();
        $job->jobId = $data['job_id'];
        $job->queue = $data['queue'];
        $job->delay = $data['delay'];
        $job->timeout = $data['timeout'];
        $job->retries = $data['retries'];
        $job->maxRetries = $data['max_retries'];
        $job->data = $data['data'];
        $job->availableAt = $data['available_at'] ? new \DateTime($data['available_at']) : null;
        $job->createdAt = $data['created_at'] ? new \DateTime($data['created_at']) : null;

        return $job;
    }
}