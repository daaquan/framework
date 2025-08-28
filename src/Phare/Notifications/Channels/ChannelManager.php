<?php

namespace Phare\Notifications\Channels;

class ChannelManager
{
    protected array $drivers = [];

    protected array $customDrivers = [];

    protected string $defaultDriver = 'mail';

    public function __construct(array $config = [])
    {
        $this->defaultDriver = $config['default'] ?? 'mail';
        $this->registerDefaultDrivers();
    }

    /**
     * Register the default notification channel drivers.
     */
    protected function registerDefaultDrivers(): void
    {
        $this->customDrivers['mail'] = function () {
            return new MailChannel();
        };

        $this->customDrivers['database'] = function () {
            return new DatabaseChannel();
        };

        $this->customDrivers['sms'] = function () {
            return new SmsChannel();
        };

        $this->customDrivers['slack'] = function () {
            return new SlackChannel();
        };
    }

    /**
     * Get a channel driver instance.
     */
    public function driver(?string $name = null): ChannelInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Create a new channel driver instance.
     */
    protected function createDriver(string $name): ChannelInterface
    {
        if (isset($this->customDrivers[$name])) {
            return $this->customDrivers[$name]();
        }

        throw new \InvalidArgumentException("Driver [{$name}] not supported.");
    }

    /**
     * Register a custom driver creator closure.
     */
    public function extend(string $name, \Closure $callback): static
    {
        $this->customDrivers[$name] = $callback;

        return $this;
    }

    /**
     * Get the default channel driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default channel driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->defaultDriver = $name;
    }

    /**
     * Get all registered drivers.
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Clear all registered drivers.
     */
    public function clearDrivers(): void
    {
        $this->drivers = [];
    }
}
