<?php

namespace Phare\Log;

use Phalcon\Config\Config;
use Phalcon\Di\DiInterface;
use Phalcon\Logger\AbstractLogger;
use Phalcon\Logger\Adapter\Noop;
use Phalcon\Logger\Adapter\Stream;
use Phalcon\Logger\Adapter\Syslog;
use Phalcon\Logger\Formatter\Json;
use Phalcon\Logger\Formatter\Line;
use Phalcon\Logger\Logger as BaseLogger;
use Phare\Foundation\AbstractApplication as Application;
use Psr\Log\LoggerInterface;

class LogManager implements LoggerInterface
{
    /**
     * The array of resolved channels.
     */
    protected array $channels = [];

    private ?string $dailyPath = null;

    /**
     * The Log levels.
     */
    protected array $levels = [
        'debug' => AbstractLogger::DEBUG,
        'info' => AbstractLogger::INFO,
        'notice' => AbstractLogger::NOTICE,
        'warning' => AbstractLogger::WARNING,
        'error' => AbstractLogger::ERROR,
        'critical' => AbstractLogger::CRITICAL,
        'alert' => AbstractLogger::ALERT,
        'emergency' => AbstractLogger::EMERGENCY,
    ];

    public function __construct(protected Application|DiInterface $app) {}

    /**
     * Parse the string level into a Monolog constant.
     *
     * @param array|Config $config
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    protected function level($config)
    {
        $level = $config['level'] ?? 'debug';

        if (isset($this->levels[$level])) {
            return $this->levels[$level];
        }

        throw new \InvalidArgumentException('Invalid log level.');
    }

    /**
     * System is unusable.
     *
     * @param string $message
     */
    public function emergency($message, array $context = []): void
    {
        $this->driver()->emergency($message, $context);
    }

    /**
     * Get a log driver instance.
     *
     * @param string|null $driver
     * @return LoggerInterface
     */
    public function driver($driver = null)
    {
        return $this->get($this->parseDriver($driver));
    }

    /**
     * Attempt to get the log from the local cache.
     *
     * @param string $name
     * @return LoggerInterface
     */
    protected function get($name, ?array $config = null)
    {
        try {
            return $this->channels[$name] ??= $this->resolve($name, $config);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to create configured logger. ' . $exception->getMessage());
        }
    }

    /**
     * Resolve the given log instance by name.
     *
     * @param string $name
     * @return \Psr\Log\LoggerInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name, ?array $config = null)
    {
        $config ??= $this->configurationFor($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Log [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = "{$config['driver']}Driver";

        if (method_exists($this, $driverMethod)) {
            return new Logger($this->{$driverMethod}($config));
        }

        throw new \InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    protected function singleDriver($config)
    {
        $handler = $config['handler'] ?? Stream::class;
        $stream = new $handler($config['path']);
        $logger = new BaseLogger($config['driver'], ['main' => $stream]);

        return $logger->setLogLevel($this->level($config));
    }

    protected function dailyDriver($config)
    {
        $path = $this->dailyPath($config['path']);

        $handler = $config['handler'] ?? Stream::class;
        $stream = new $handler($path);

        $logger = new BaseLogger($config['driver'], ['main' => $stream]);

        return $logger->setLogLevel($this->level($config));
    }

    private function dailyPath($path)
    {
        if ($this->dailyPath === null) {
            $fragments = pathinfo($path);
            $filename = $fragments['filename'];

            $now = date('Y-m-d');
            $this->dailyPath = $fragments['dirname'] . '/' .
                $filename . '-' . $now . '.' . $fragments['extension'];
        }

        return $this->dailyPath;
    }

    protected function stderrDriver($config)
    {
        $handler = $config['handler'] ?? Stream::class;
        $stream = $config['with']['stream'] ?? 'php://stderr';
        $formatter = $config['formatter'] ?? null;
        if ($formatter !== null) {
            $formatterClass = $this->formatterClass($formatter);
            $stream->setFormatter(new $formatterClass());
        }
        $logger = new BaseLogger($config['driver'], ['main' => new $handler($stream)]);

        return $logger->setLogLevel($this->level($config));
    }

    private function formatterClass(string $formatter)
    {
        return match ($formatter) {
            'json' => Json::class,
            'line' => Line::class,
            default => throw new \InvalidArgumentException('Invalid log formatter'),
        };
    }

    protected function noopDriver($config)
    {
        $logger = new BaseLogger($config['driver'], ['main' => new Noop()]);

        return $logger->setLogLevel($this->level($config));
    }

    protected function syslogDriver($config)
    {
        $handler = new Syslog(
            'sys',
            [
                'option' => LOG_CONS | LOG_NDELAY | LOG_PID,
                'facility' => LOG_USER,
            ]);
        $logger = new BaseLogger($config['driver'], ['main' => $handler]);

        return $logger->setLogLevel($this->level($config));
    }

    /**
     * Unset the given channel instance.
     *
     * @param string|null $driver
     * @return $this
     */
    public function forgetChannel($driver = null)
    {
        $driver = $this->parseDriver($driver);

        if (isset($this->channels[$driver])) {
            unset($this->channels[$driver]);
        }

        return $this;
    }

    /**
     * Parse the driver name.
     *
     * @param string|null $driver
     * @return string|null
     */
    protected function parseDriver($driver)
    {
        $driver ??= $this->getDefaultDriver();

        if ($this->app->runningUnitTests()) {
            $driver ??= 'noop';
        }

        return $driver;
    }

    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * Get the log connection configuration.
     *
     * @param string $name
     * @return array
     */
    protected function configurationFor($name)
    {
        return $this->app['config']?->path("logging.channels.{$name}");
    }

    /**
     * Get the default log driver name.
     *
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->app['config']?->path('logging.default');
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     */
    public function alert($message, array $context = []): void
    {
        $this->driver()->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     */
    public function critical($message, array $context = []): void
    {
        $this->driver()->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     */
    public function error($message, array $context = []): void
    {
        $this->driver()->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     */
    public function warning($message, array $context = []): void
    {
        $this->driver()->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     */
    public function notice($message, array $context = []): void
    {
        $this->driver()->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     */
    public function info($message, array $context = []): void
    {
        $this->driver()->info($message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     */
    public function debug($message, array $context = []): void
    {
        $this->driver()->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     */
    public function log($level, $message, array $context = []): void
    {
        $this->driver()->log($level, $message, $context);
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
