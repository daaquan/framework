<?php

namespace Phare\Hashing;

class ArgonHasher implements HasherInterface
{
    protected int $memory = 1024;

    protected int $time = 2;

    protected int $threads = 2;

    public function __construct(array $options = [])
    {
        $this->memory = $options['memory'] ?? $this->memory;
        $this->time = $options['time'] ?? $this->time;
        $this->threads = $options['threads'] ?? $this->threads;
    }

    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, $this->algorithm(), [
            'memory_cost' => $options['memory'] ?? $this->memory,
            'time_cost' => $options['time'] ?? $this->time,
            'threads' => $options['threads'] ?? $this->threads,
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Argon2 hashing not supported.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, $this->algorithm(), [
            'memory_cost' => $options['memory'] ?? $this->memory,
            'time_cost' => $options['time'] ?? $this->time,
            'threads' => $options['threads'] ?? $this->threads,
        ]);
    }

    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    protected function algorithm(): string
    {
        return PASSWORD_ARGON2I;
    }
}
