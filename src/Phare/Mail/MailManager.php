<?php

namespace Phare\Mail;

use Phare\Container\Container;
use Phare\Support\Manager;

class MailManager extends Manager
{
    /**
     * Sentinel driver name for the top-level `mail` config block, used when
     * no `mail.default` key is configured.
     */
    protected const DEFAULT_MAILER = '__default';

    public function __construct(Container $app)
    {
        parent::__construct($app);
    }

    /**
     * Resolve a configured mailer (Laravel-parity alias for driver()).
     */
    public function mailer(?string $name = null): Mailer
    {
        return $this->driver($name);
    }

    /**
     * Default driver name. Falls back to the {@see DEFAULT_MAILER} sentinel so
     * the top-level `mail` config still resolves when `mail.default` is unset.
     */
    public function getDefaultDriver(): string
    {
        $value = $this->resolveConfigKey('mail.default');

        return is_string($value) ? $value : self::DEFAULT_MAILER;
    }

    /**
     * Default mailer name, or null when no `mail.default` is configured.
     */
    public function getDefaultMailer(): ?string
    {
        $value = $this->resolveConfigKey('mail.default');

        return is_string($value) ? $value : null;
    }

    protected function createDriver(string $name): Mailer
    {
        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name);
        }

        if ($name === self::DEFAULT_MAILER) {
            return new Mailer($this->topLevelConfig());
        }

        return new Mailer($this->configFor($name));
    }

    /**
     * @return array<string, mixed>
     */
    protected function configFor(string $name): array
    {
        $config = $this->resolveConfigKey("mail.mailers.{$name}");

        if ($config === null) {
            throw new \InvalidArgumentException("Mailer [{$name}] is not defined.");
        }

        return $this->normalize($config);
    }

    /**
     * @return array<string, mixed>
     */
    protected function topLevelConfig(): array
    {
        return $this->normalize($this->resolveConfigKey('mail') ?? []);
    }

    protected function resolveConfigKey(string $key): mixed
    {
        if (!$this->container->bound('config')) {
            return null;
        }

        $config = $this->container->make('config');

        if (is_array($config)) {
            return self::dig($config, $key);
        }

        if (is_object($config) && method_exists($config, 'path')) {
            return $config->path($key);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalize(mixed $config): array
    {
        if (is_array($config)) {
            return $config;
        }

        if (is_object($config) && method_exists($config, 'toArray')) {
            return $config->toArray();
        }

        return (array)$config;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function dig(array $config, string $key): mixed
    {
        $segments = explode('.', $key);
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
