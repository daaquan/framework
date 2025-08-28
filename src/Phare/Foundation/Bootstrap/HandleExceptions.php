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

        $debugEnabled = $this->shouldEnableDebug($app);

        // Configure error reporting level
        // Phalcon uses some calls deprecated in PHP 8.2 and above, so exclude E_DEPRECATED
        error_reporting($debugEnabled ? E_ALL & E_DEPRECATED : -1);

        // Configure error display
        ini_set('display_errors', $debugEnabled ? 'On' : 'Off');
        ini_set('phalcon.warning.enable', $debugEnabled ? $this->getPhalconWarningEnabled($app) : false);

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
        } catch (\Exception $reportException) {
            // If reporting fails, log to error_log as fallback
            error_log("Exception reporting failed: " . $reportException->getMessage());
            error_log("Original exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }

        try {
            if ($this->app->runningInConsole()) {
                $this->renderForConsole($e);
            } else {
                $this->renderHttpResponse($e);
            }
        } catch (\Throwable $renderException) {
            // If rendering fails, use fallback error display
            error_log("Exception rendering failed: " . $renderException->getMessage());
            $this->renderFallbackError($e);
        }
    }

    /**
     * Render the exception to the console.
     *
     * @return void
     */
    protected function renderForConsole(\Throwable $e)
    {
        try {
            $this->getExceptionHandler()->renderForConsole(new ConsoleOutput(), $e);
        } catch (\Throwable $renderException) {
            // Fallback console rendering
            echo "Fatal Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "Trace:\n" . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * Render the exception as an HTTP response and send it.
     *
     * @return void
     */
    protected function renderHttpResponse(\Throwable $e)
    {
        try {
            $this->getExceptionHandler()->render($this->app['request'], $e)->send();
        } catch (\Throwable $renderException) {
            // If exception handler fails, use fallback
            $this->renderFallbackError($e);
        }
    }

    /**
     * Render a fallback error response when normal rendering fails.
     *
     * @param \Throwable $e
     * @return void
     */
    protected function renderFallbackError(\Throwable $e): void
    {
        $isDebug = $this->shouldEnableDebug($this->app);
        
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(500);
        
        if ($isDebug) {
            // Show detailed error in debug mode
            echo '<!DOCTYPE html>';
            echo '<html><head><title>Application Error</title>';
            echo '<style>body{font-family:monospace;margin:20px;}pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;overflow:auto;}</style>';
            echo '</head><body>';
            echo '<h1>Application Error</h1>';
            echo '<p><strong>Exception:</strong> ' . htmlspecialchars(get_class($e)) . '</p>';
            echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
            echo '<h2>Stack Trace:</h2>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            
            // Show previous exception if exists
            if ($previous = $e->getPrevious()) {
                echo '<h2>Previous Exception:</h2>';
                echo '<p><strong>Exception:</strong> ' . htmlspecialchars(get_class($previous)) . '</p>';
                echo '<p><strong>Message:</strong> ' . htmlspecialchars($previous->getMessage()) . '</p>';
                echo '<p><strong>File:</strong> ' . htmlspecialchars($previous->getFile()) . '</p>';
                echo '<p><strong>Line:</strong> ' . $previous->getLine() . '</p>';
            }
            
            echo '</body></html>';
        } else {
            // Simple error page for production
            echo '<!DOCTYPE html>';
            echo '<html><head><title>Internal Server Error</title></head>';
            echo '<body><h1>Internal Server Error</h1>';
            echo '<p>The server encountered an error and could not complete your request.</p>';
            echo '</body></html>';
        }
        
        exit(1);
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

    /**
     * Determine if debug mode should be enabled.
     * Safe method that handles missing config gracefully.
     *
     * @param Application|DiInterface $app
     * @return bool
     */
    protected function shouldEnableDebug($app): bool
    {
        try {
            // Check testing environment first
            if ($app->environment('testing')) {
                return true;
            }

            // Try to get debug setting from config
            if (isset($app['config']) && $app['config'] !== null) {
                $debug = $app['config']->path('app.debug');
                if ($debug !== null) {
                    return (bool) $debug;
                }
            }

            // Fallback to environment variables
            $envDebug = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');
            if ($envDebug !== false) {
                return in_array(strtolower($envDebug), ['true', '1', 'on', 'yes']);
            }

            // Check if we're in a local/development environment
            $envEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
            if (in_array($envEnv, ['local', 'development', 'dev'])) {
                return true;
            }

            // Default to false for production safety
            return false;

        } catch (\Throwable $e) {
            // If anything goes wrong, log it and default to safe mode
            error_log("Debug detection failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Phalcon warning setting safely.
     *
     * @param Application|DiInterface $app
     * @return bool
     */
    protected function getPhalconWarningEnabled($app): bool
    {
        try {
            if (isset($app['config']) && $app['config'] !== null) {
                $phalconConfig = $app['config']->path('app.phalcon');
                if (is_array($phalconConfig)) {
                    return $phalconConfig['warning.enable'] ?? false;
                }
            }
            return false;
        } catch (\Throwable $e) {
            error_log("Phalcon warning config detection failed: " . $e->getMessage());
            return false;
        }
    }
}
