<?php

namespace Phare\Console;

use Phalcon\Config\Config as PhalconConfig;
use Phalcon\Di\Di;
use Phalcon\Di\Injectable;
use Phare\Console\Exceptions\InvalidConfig;
use Phare\Console\Helpers\PathHelpers;

class Config extends Injectable
{
    use PathHelpers;

    private const DEFAULTS = [
        'migratorType' => 'fileDate',
        'migrationRepository' => 'database',
    ];

    private static ?Config $instance = null;

    protected PhalconConfig $config;

    protected array $original = [];

    private function __construct() {}

    /** Get the singleton instance */
    public static function getInstance(): Config
    {
        if (!self::$instance) {
            $instance = new self();
            $config = $instance->getDI()->getShared('config');
            self::$instance = $instance->setConfig($config);
        }

        return self::$instance;
    }

    /** Object-like access (e.g. $config->foo) */
    public function __get($key): mixed
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return $this->getDefault($key);
    }

    /** Retrieve default value */
    public function getDefault(string $key): mixed
    {
        return self::DEFAULTS[$key] ?? null;
    }

    /** Set the configuration */
    public function setConfig($userConfig = null, bool $merge = true): static
    {
        $this->config = Di::getDefault()->get('config');

        if (is_string($userConfig)) {
            $this->config = $this->getNested(explode('.', $userConfig));
        } elseif (is_array($userConfig)) {
            $userConfig = new PhalconConfig($userConfig);
            if ($merge) {
                $this->merge($userConfig);
            } else {
                $this->config = $userConfig;
            }
        } elseif ($userConfig instanceof PhalconConfig) {
            if ($merge) {
                $this->merge($userConfig);
            } else {
                $this->config = $userConfig;
            }
        }

        $this->original = $this->toArray();

        return $this;
    }

    /** Retrieve nested values (foo.bar.baz) */
    public function getNested(array $keys): mixed
    {
        $current = $this->config;
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return $this->getDefault($key);
            }
            $current = $current->$key;
        }

        return $current;
    }

    /** Merge configuration */
    public function merge(PhalconConfig $config): void
    {
        $this->config = $this->config->merge($config);
    }

    /** Return as array */
    public function toArray(): array
    {
        if ($this->config instanceof PhalconConfig) {
            return $this->config->toArray();
        }

        return (array)$this->config;
    }

    /** Array access (get) */
    public function get($key)
    {
        if (is_array($key)) {
            return $this->getNested($key);
        }

        return $this->$key;
    }

    /** Array access (set) */
    public function set($keys, $value)
    {
        $temp = &$this->config;
        $keys = $this->makeArray($keys);
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                return $temp[$key] = $value;
            }
            if (!isset($temp[$key]) || !is_object($temp[$key])) {
                $temp[$key] = new PhalconConfig();
            }
            $temp = &$temp[$key];
        }

        return null;
    }

    protected function makeArray(mixed $value): array
    {
        return is_array($value) ? $value : [$value];
    }

    /** Array access (unset) */
    public function remove($keys): void
    {
        $temp = &$this->config;
        $keys = $this->makeArray($keys);
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                unset($temp[$key]);
            } else {
                if (!isset($temp[$key]) || !is_object($temp[$key])) {
                    return;
                }
                $temp = &$temp[$key];
            }
        }
    }

    /** Restore to original state */
    public function refresh(): void
    {
        $this->config = $this->config::__set_state($this->original);
    }

    /** Validate that settings exist */
    public function validate(array $settings): void
    {
        if (!$this->has($settings)) {
            throw InvalidConfig::configValueNotFound(implode(' -> ', $settings));
        }
    }

    /** Determine if settings exist */
    public function has($value): bool
    {
        $current = $this->config;
        foreach ($this->makeArray($value) as $configItem) {
            if (!isset($current[$configItem])) {
                return false;
            }
            $current = $current->$configItem;
        }

        return true;
    }

    /** Number of keys */
    public function count(): int
    {
        return is_object($this->config)
            ? $this->config->count()
            : count($this->config);
    }
}
