<?php

namespace Phare\Hashing;

class HashManager
{
    protected array $drivers = [];

    protected string $defaultDriver = 'bcrypt';

    public function __construct(string $defaultDriver = 'bcrypt')
    {
        $this->defaultDriver = $defaultDriver;
        $this->registerDefaultDrivers();
    }

    protected function registerDefaultDrivers(): void
    {
        $this->drivers['bcrypt'] = new BcryptHasher();
        $this->drivers['argon'] = new ArgonHasher();
        $this->drivers['argon2i'] = new Argon2iHasher();
        $this->drivers['argon2id'] = new Argon2idHasher();
    }

    public function driver(?string $driver = null): HasherInterface
    {
        $driver = $driver ?: $this->defaultDriver;

        if (!isset($this->drivers[$driver])) {
            throw new \InvalidArgumentException("Hash driver [{$driver}] not found.");
        }

        return $this->drivers[$driver];
    }

    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        return $this->driver()->check($value, $hashedValue, $options);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    public function info(string $hashedValue): array
    {
        return $this->driver()->info($hashedValue);
    }

    public function extend(string $driver, HasherInterface $hasher): void
    {
        $this->drivers[$driver] = $hasher;
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
