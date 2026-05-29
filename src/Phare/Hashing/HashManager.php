<?php

namespace Phare\Hashing;

use Closure;
use Phalcon\Config\Config;
use Phare\Container\Container;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Support\Manager;

class HashManager extends Manager
{
    protected string $defaultDriver = 'bcrypt';

    /**
     * Strong Argon2 cost factors applied when no config is bound.
     * Mirrors Laravel's shipped config/hashing.php (argon).
     */
    private const ARGON_DEFAULTS = ['memory' => 65536, 'time' => 4, 'threads' => 1];

    /**
     * Strong Bcrypt cost applied when no config is bound.
     */
    private const BCRYPT_DEFAULTS = ['rounds' => 12];

    /**
     * @param string|ContainerContract|null $defaultDriver Default driver name,
     *                                                     or the application container.
     */
    public function __construct(string|ContainerContract|null $defaultDriver = null)
    {
        if ($defaultDriver instanceof ContainerContract) {
            parent::__construct($defaultDriver);
            $this->defaultDriver = (string)$this->configValue('hashing.driver', 'bcrypt');
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
        return new BcryptHasher(array_merge(
            self::BCRYPT_DEFAULTS,
            $this->configArray('hashing.bcrypt')
        ));
    }

    protected function createArgonDriver(): HasherInterface
    {
        return new ArgonHasher(array_merge(
            self::ARGON_DEFAULTS,
            $this->configArray('hashing.argon')
        ));
    }

    protected function createArgon2iDriver(): HasherInterface
    {
        return new Argon2iHasher(array_merge(
            self::ARGON_DEFAULTS,
            $this->configArray('hashing.argon')
        ));
    }

    protected function createArgon2idDriver(): HasherInterface
    {
        return new Argon2idHasher(array_merge(
            self::ARGON_DEFAULTS,
            $this->configArray('hashing.argon')
        ));
    }

    /**
     * Read a config sub-array from the bound config repository.
     * Returns [] when no config is bound so strong defaults win.
     *
     * @return array<string, mixed>
     */
    protected function configArray(string $key): array
    {
        $value = $this->configValue($key);

        if ($value instanceof Config) {
            return $value->toArray();
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Resolve a dot-notation key from the bound config repository,
     * tolerating the empty-array fallback used when no config is bound.
     */
    protected function configValue(string $key, mixed $default = null): mixed
    {
        $config = $this->config;

        if ($config instanceof Config) {
            return $config->path($key, $default);
        }

        return $default;
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
