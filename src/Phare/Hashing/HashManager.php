<?php

namespace Phare\Hashing;

use Closure;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Support\Manager;

class HashManager extends Manager
{
    protected string $defaultDriver = 'bcrypt';

    /**
     * @param string|ContainerContract|null $defaultDriver Default driver name,
     *                                                     or the application container.
     */
    public function __construct(string|ContainerContract|null $defaultDriver = null)
    {
        if ($defaultDriver instanceof ContainerContract) {
            parent::__construct($defaultDriver);
            $this->defaultDriver = (string)config('hashing.driver', 'bcrypt');
        } else {
            parent::__construct($this->resolveContainer());
            $this->defaultDriver = $defaultDriver ?? 'bcrypt';
        }
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    protected function createBcryptDriver(): HasherInterface
    {
        return new BcryptHasher();
    }

    protected function createArgonDriver(): HasherInterface
    {
        return new ArgonHasher();
    }

    protected function createArgon2iDriver(): HasherInterface
    {
        return new Argon2iHasher();
    }

    protected function createArgon2idDriver(): HasherInterface
    {
        return new Argon2idHasher();
    }

    public function make(#[\SensitiveParameter] string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    public function check(#[\SensitiveParameter] string $value, string $hashedValue, array $options = []): bool
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

    /**
     * Register a custom hasher. Accepts a ready hasher instance (legacy API)
     * or a Closure factory (Laravel parity).
     */
    public function extend(string $driver, Closure|HasherInterface $hasher): static
    {
        if ($hasher instanceof HasherInterface) {
            $this->customCreators[$driver] = static fn (): HasherInterface => $hasher;
        } else {
            $this->customCreators[$driver] = Closure::bind($hasher, $this, static::class);
        }

        return $this;
    }

    protected function resolveContainer(): ContainerContract
    {
        $app = app();

        if ($app instanceof ContainerContract) {
            return $app;
        }

        return new Container();
    }
}
