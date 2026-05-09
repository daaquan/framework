<?php

namespace Phare\Mail;

use Phare\Container\Container;

class MailManager
{
    protected array $mailers = [];

    public function __construct(protected Container $app) {}

    public function mailer(?string $name = null): Mailer
    {
        $name = $name ?: $this->getDefaultMailer();

        if ($name === null) {
            return $this->mailers['__default'] ??= new Mailer($this->topLevelConfig());
        }

        if (isset($this->mailers[$name])) {
            return $this->mailers[$name];
        }

        return $this->mailers[$name] = new Mailer($this->configFor($name));
    }

    protected function configFor(string $name): array
    {
        $config = $this->resolveConfigKey("mail.mailers.{$name}");

        if ($config === null) {
            throw new \InvalidArgumentException("Mailer [{$name}] is not defined.");
        }

        return $this->normalize($config);
    }

    protected function topLevelConfig(): array
    {
        return $this->normalize($this->resolveConfigKey('mail') ?? []);
    }

    public function getDefaultMailer(): ?string
    {
        $value = $this->resolveConfigKey('mail.default');

        return is_string($value) ? $value : null;
    }

    protected function resolveConfigKey(string $key): mixed
    {
        if (!$this->app->has('config')) {
            return null;
        }

        $config = $this->app->make('config');

        if (is_array($config)) {
            return self::dig($config, $key);
        }

        if (is_object($config) && method_exists($config, 'path')) {
            return $config->path($key);
        }

        return null;
    }

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

    public function __call(string $method, array $parameters): mixed
    {
        return $this->mailer()->$method(...$parameters);
    }
}
