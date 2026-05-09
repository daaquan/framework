<?php

declare(strict_types=1);

namespace Phare\Auth;

use Closure;
use InvalidArgumentException;
use Phalcon\Config\Config;
use Phalcon\Config\ConfigInterface;
use Phare\Contracts\Foundation\Container as ContainerContract;

/**
 * Owns the configured auth guards and resolves them on demand.
 *
 * `guard(?string $name)` returns a guard instance for the requested name (or
 * the default when $name is null). Guard instances are lazily constructed from
 * `auth.guards.{name}` config and cached for the lifetime of the manager.
 *
 * Method calls that are not declared on this class are forwarded to the
 * default guard so existing call sites — `app('auth')->user()`, attempt(),
 * login(), check(), etc. — keep working unchanged.
 */
class AuthManager
{
    /**
     * @var array<string, object>
     */
    protected array $guards = [];

    /**
     * @var array<string, Closure>
     */
    protected array $customDrivers = [];

    public function __construct(private ContainerContract $app) {}

    public function guard(?string $name = null): object
    {
        $name = $name ?? $this->getDefaultDriver();

        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }

        return $this->guards[$name] = $this->resolve($name);
    }

    public function getDefaultDriver(): string
    {
        return (string)config('auth.defaults.guard', 'web');
    }

    /**
     * Register a custom guard driver factory.
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customDrivers[$driver] = $callback;

        return $this;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->guard()->{$method}(...$arguments);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolve(string $name): object
    {
        $config = $this->normalizeConfig(config("auth.guards.{$name}"));

        if ($config === [] || !isset($config['driver'])) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        $driver = (string)$config['driver'];

        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($this->app, $name, $config);
        }

        return match ($driver) {
            'session' => $this->createSessionDriver($name, $config),
            default => throw new InvalidArgumentException("Auth driver [{$driver}] for guard [{$name}] is not supported."),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createSessionDriver(string $name, array $config): Manager
    {
        $guardConfig = $this->buildSessionGuardConfig($name, $config);

        return new Manager(
            $this->app['session'],
            $guardConfig,
            $this->app['events'] ?? null,
        );
    }

    /**
     * Build the ConfigInterface payload that the session-based Manager expects.
     *
     * Merges the per-guard configuration with the legacy top-level keys
     * (`auth.model`, `auth.session_id`) so existing apps that have not yet
     * adopted the guards/providers schema keep working.
     *
     * @param array<string, mixed> $guardConfig
     */
    protected function buildSessionGuardConfig(string $name, array $guardConfig): ConfigInterface
    {
        $authRoot = $this->normalizeConfig(config('auth'));

        $providerName = (string)($guardConfig['provider'] ?? '');
        $providerConfig = $providerName !== ''
            ? $this->normalizeConfig(config("auth.providers.{$providerName}"))
            : [];

        $model = $providerConfig['model']
            ?? $authRoot['model']
            ?? null;

        if ($model === null) {
            throw new InvalidArgumentException(
                "Auth guard [{$name}] has no resolvable user model — set auth.providers.{$providerName}.model or auth.model."
            );
        }

        $sessionId = $guardConfig['session_id']
            ?? ($authRoot['session_id'] ?? "auth.{$name}");

        return new Config([
            'model' => $model,
            'session_id' => $sessionId,
        ]);
    }

    protected function normalizeConfig(mixed $value): array
    {
        if ($value instanceof Config) {
            return $value->toArray();
        }

        return is_array($value) ? $value : [];
    }
}
