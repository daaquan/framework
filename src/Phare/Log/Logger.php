<?php

namespace Phare\Log;

use Phalcon\Logger\Adapter\AdapterInterface;
use Phalcon\Logger\Logger as BaseLogger;
use Phalcon\Logger\LoggerInterface;
use Phare\Contracts\Support\Arrayable;
use Phare\Contracts\Support\Jsonable;

class Logger implements \Psr\Log\LoggerInterface, LoggerInterface
{
    protected array $context = [];

    public function __construct(protected BaseLogger $logger) {}

    public function alert($message, array $context = []): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    public function emergency($message, array $context = []): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    public function getAdapter(string $name): AdapterInterface
    {
        return $this->logger->getAdapter($name);
    }

    public function getAdapters(): array
    {
        return $this->logger->getAdapters();
    }

    public function getLogLevel(): int
    {
        return $this->logger->getLogLevel();
    }

    public function getName(): string
    {
        return $this->logger->getName();
    }

    public function channel(string $name)
    {
        // For now, return the same logger instance
        // In a full implementation, this would return a new logger instance
        // configured for the specified channel
        return $this;
    }

    public function info($message, array $context = []): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->writeLog($level, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * Dynamically pass log calls into the writer.
     */
    public function write($level, $message, array $context = []): void
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * Write a message to the log.
     */
    protected function writeLog($level, $message, $context): void
    {
        $this->logger->{$level}(
            $this->formatMessage($message),
            array_merge($this->context, $context)
        );
    }

    /**
     * Format the parameters for the logger.
     */
    protected function formatMessage($message)
    {
        if (is_array($message)) {
            return var_export($message, true);
        }

        if ($message instanceof Jsonable) {
            return $message->toJson();
        }

        if ($message instanceof Arrayable) {
            return var_export($message->toArray(), true);
        }

        return (string)$message;
    }

    /**
     * Add context to all future logs.
     */
    public function withContext(array $context = [])
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Flush the existing context array.
     */
    public function withoutContext()
    {
        $this->context = [];

        return $this;
    }
}
