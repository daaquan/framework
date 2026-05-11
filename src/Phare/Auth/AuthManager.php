<?php

declare(strict_types=1);

namespace Phare\Auth;

use InvalidArgumentException;
use Phalcon\Config\Config;
use Phalcon\Config\ConfigInterface;
use Phare\Contracts\Foundation\Container as ContainerContract;
use Phare\Support\Manager as BaseManager;

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
class AuthManager extends BaseManager
{
    public function __construct(ContainerContract $app)
    {
        parent::__construct($app);
    }

    public function guard(?string $name = null): object
    {
        return $this->driver($name);
    }

    public function getDefaultDriver(): string
    {
        return (string)config('auth.defaults.guard', 'web');
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createDriver(string $name): object
    {
        $config = $this->normalizeConfig(config("auth.guards.{$name}"));

        if ($config === [] || !isset($config['driver'])) {
            $config = $this->legacyDefaultGuardConfig($name);
        }

        $driver = (string)$config['driver'];

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($this->container, $name, $config);
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
            $this->container['session'],
            $guardConfig,
            $this->container['events'] ?? null,
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

    /**
     * @return array<string, mixed>
     */
    protected function legacyDefaultGuardConfig(string $name): array
    {
        $authRoot = $this->normalizeConfig(config('auth'));

        if ($name === $this->getDefaultDriver() && isset($authRoot['model'])) {
            return ['driver' => 'session'];
        }

        throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
    }
}
