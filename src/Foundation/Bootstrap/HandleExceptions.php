<?php

namespace Phare\Foundation\Bootstrap;

use Phalcon\Di\DiInterface;
use Phare\Console\Output\Logger as ConsoleOutput;
use Phare\Contracts\Debug\ExceptionHandler;
use Phare\Contracts\Foundation\Application;
use Symfony\Component\ErrorHandler\Error\FatalError;

/**
 * Error handling bootstrapper.
 */
class HandleExceptions
{
    protected Application|DiInterface $app;

    public function register(Application|DiInterface $app): void
    {
        $this->app = $app;

        $debugEnabled = $app->environment('testing') || $app['config']?->path('app.debug');

        // Configure error reporting level
        // Phalcon uses some calls deprecated in PHP 8.2 and above, so exclude E_DEPRECATED
        error_reporting($debugEnabled ? E_ALL & E_DEPRECATED & E_STRICT : -1);

        // Configure error display
        ini_set('display_errors', $debugEnabled ? 'On' : 'Off');
        ini_set('phalcon.warning.enable', $debugEnabled ? ($app['config']?->path('app.phalcon')['warning.enable'] ?? false) : false);

        // Register error handler
        set_error_handler([$this, 'handleError']);

        // Register exception handler
        set_exception_handler([$this, 'handleException']);

        // Register shutdown handler
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Report deprecation errors or convert PHP errors to ErrorException.
     *
     * @param int $level
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @return void
     *
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        if ($this->isDeprecation($level)) {
            $this->handleDeprecation($message, $file, $line);

            return;
        }

        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Report deprecations to the "deprecations" logger.
     *
     * @param string $message
     * @param string $file
     * @param int $line
     * @return void
     */
    public function handleDeprecation($message, $file, $line)
    {
        if (!$this->app->hasBeenBootstrapped() || $this->app->runningUnitTests()) {
            return;
        }

        try {
            /** @var \Phare\Log\Logger $logger */
            $logger = $this->app->make('log');
        } catch (\Exception $e) {
            return;
        }

        $this->ensureDeprecationLoggerIsConfigured();

        with($logger->channel('deprecations'), function ($log) use ($message, $file, $line) {
            $log->warning(sprintf('%s in %s on line %s',
                $message, $file, $line
            ));
        });
    }

    /**
     * Ensure that the "deprecations" logger is configured.
     *
     * @return void
     */
    protected function ensureDeprecationLoggerIsConfigured()
    {
        with($this->app['config'], function ($config) {
            if ($config->get('logging.channels.deprecations')) {
                return;
            }

            $driver = $config->get('logging.deprecations') ?? 'null';

            $config->set('logging.channels.deprecations', $config->get("logging.channels.{$driver}"));
        });
    }

    /**
     * Handle an uncaught exception.
     *
     * Note: Many exceptions are handled by the HTTP and console kernels, but fatal errors are not regular exceptions and must be handled differently.
     *
     * @return void
     */
    public function handleException(\Throwable $e)
    {
        try {
            $this->getExceptionHandler()->report($e);
        } catch (\Exception $e) {
            //
        }

        if ($this->app->runningInConsole()) {
            $this->renderForConsole($e);
        } else {
            $this->renderHttpResponse($e);
        }
    }

    /**
     * Render the exception to the console.
     *
     * @return void
     */
    protected function renderForConsole(\Throwable $e)
    {
        $this->getExceptionHandler()->renderForConsole(new ConsoleOutput(), $e);
    }

    /**
     * Render the exception as an HTTP response and send it.
     *
     * @return void
     */
    protected function renderHttpResponse(\Throwable $e)
    {
        $this->getExceptionHandler()->render($this->app['request'], $e)->send();
    }

    /**
     * Handle the PHP shutdown event.
     *
     * @return void
     */
    public function handleShutdown()
    {
        if (!is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalErrorFromPhpError($error, 0));
        }
    }

    /**
     * Create a FatalError instance from an array.
     *
     * @param int|null $traceOffset
     * @return FatalError
     */
    protected function fatalErrorFromPhpError(array $error, $traceOffset = null)
    {
        return new FatalError($error['message'], 0, $error, $traceOffset);
    }

    /**
     * Determine if the error level is a deprecation.
     *
     * @param int $level
     * @return bool
     */
    protected function isDeprecation($level)
    {
        return in_array($level, [E_DEPRECATED, E_USER_DEPRECATED]);
    }

    /**
     * Determine if the error level is fatal.
     *
     * @param int $type
     * @return bool
     */
    protected function isFatal($type)
    {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE]);
    }

    /**
     * Get the exception handler instance.
     *
     * @return ExceptionHandler
     */
    protected function getExceptionHandler()
    {
        return $this->app->make(ExceptionHandler::class);
    }
}
