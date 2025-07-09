<?php

namespace Phare\Debug;

use Phare\Contracts\Foundation\Application;

class DebugLogger
{
    protected float $startTime;

    protected int $startMemoryUsage;

    protected bool $shouldLog = false;

    protected ?string $format = null;

    public function __construct(protected Application $app, float $startTime = 0)
    {
        $this->shouldLog = (bool)$this->app['config']->path('app.debug', false);

        if (!$this->shouldLog) {
            return;
        }

        $this->startTime = $startTime ?: $_SERVER['REQUEST_TIME_FLOAT'];
        $this->startMemoryUsage = memory_get_usage();
    }

    protected function formatLog(string $message, array $context = []): string
    {
        if ($this->format === null) {
            $request = $this->app['request'];
            $uri = parse_url($request->getURI(), PHP_URL_PATH) ?: '/';
            $method = $request->getMethod();

            $this->format = "[$method @ $uri] %s %s";
        }

        $formattedContext = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return sprintf($this->format, $message, $formattedContext);
    }

    protected function logIfDebug(string $level, string $message, array $context = [])
    {
        if (!$this->shouldLog) {
            return;
        }

        $context['execution_time'] = $this->getExecutionTime();
        $context['memory_usage'] = $this->getMemoryUsage();

        $this->app['log']->$level($this->formatLog($message, $context));
    }

    protected function getExecutionTime(): string
    {
        return round((microtime(true) - $this->startTime) * 1000, 3) . 'ms';
    }

    protected function getMemoryUsage(): string
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        $size = memory_get_peak_usage() - $this->startMemoryUsage;

        return round($size / (1024 ** ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }

    public function logServiceProviderBooting()
    {
        $this->logIfDebug('debug', 'ServiceProvider booting...');
    }

    public function logRouteMounted()
    {
        $this->logIfDebug('debug', 'Route mounted');
    }

    public function logMiddlewareStart($middleware)
    {
        $this->logIfDebug('debug', "Entering middleware: $middleware");
    }

    public function logMiddlewareEnd($middleware)
    {
        $this->logIfDebug('debug', "Exiting middleware: $middleware");
    }

    public function logModelEvent($eventName, $modelName)
    {
        $this->logIfDebug('debug', "Model event: $eventName for model: $modelName");
    }

    public function logCommandStart($commandName)
    {
        $this->logIfDebug('debug', "Command starting: $commandName");
    }

    public function logCommandEnd($commandName)
    {
        $this->logIfDebug('debug', "Command ended: $commandName");
    }

    public function logTerminate()
    {
        $this->logIfDebug('debug', 'Terminating application');
    }

    public function log(string $message, array $context = [])
    {
        $this->logIfDebug('debug', $message, $context);
    }
}
