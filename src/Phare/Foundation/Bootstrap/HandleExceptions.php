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
            // Try to use Whoops if available
            if ($this->renderWithWhoops($e)) {
                return;
            }
            
            // Fallback to custom detailed error page
            $this->renderDetailedError($e);
        } else {
            // Simple error page for production
            $this->renderSimpleError();
        }
        
        exit(1);
    }

    /**
     * Attempt to render error using Whoops if available.
     *
     * @param \Throwable $e
     * @return bool True if Whoops was used, false otherwise
     */
    protected function renderWithWhoops(\Throwable $e): bool
    {
        if (!class_exists('\Whoops\Run')) {
            return false;
        }

        try {
            $whoops = new \Whoops\Run;
            $whoops->allowQuit(false);
            $whoops->writeToOutput(false);
            
            // Add pretty page handler for web requests
            $handler = new \Whoops\Handler\PrettyPageHandler;
            
            // Set application name if available
            try {
                $appName = $this->app['config']?->path('app.name') ?? 'Phare Application';
                $handler->setApplicationName($appName);
            } catch (\Throwable $configException) {
                $handler->setApplicationName('Phare Application');
            }
            
            // Add custom CSS for better styling
            $handler->addResourcePath(__DIR__ . '/../../../../resources/whoops');
            
            $whoops->prependHandler($handler);
            
            // Generate and output the error page
            $output = $whoops->handleException($e);
            echo $output;
            
            return true;
            
        } catch (\Throwable $whoopsException) {
            // If Whoops fails, log the error and return false to use fallback
            error_log("Whoops rendering failed: " . $whoopsException->getMessage());
            return false;
        }
    }

    /**
     * Render detailed error page (fallback when Whoops is not available).
     *
     * @param \Throwable $e
     * @return void
     */
    protected function renderDetailedError(\Throwable $e): void
    {
        echo '<!DOCTYPE html>';
        echo '<html><head><title>Application Error</title>';
        echo '<meta charset="utf-8">';
        echo '<style>';
        echo 'body{font-family:sans-serif;margin:0;padding:20px;background:#f8f9fa;}';
        echo '.container{max-width:1200px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}';
        echo 'h1{color:#dc3545;margin-top:0;}';
        echo '.exception-info{background:#f8f9fa;padding:15px;border-radius:4px;margin:20px 0;}';
        echo '.exception-info strong{color:#495057;}';
        echo 'pre{background:#212529;color:#f8f9fa;padding:15px;border-radius:4px;overflow:auto;font-size:14px;line-height:1.4;}';
        echo '.previous-exception{margin-top:30px;padding-top:20px;border-top:2px solid #dee2e6;}';
        echo '</style>';
        echo '</head><body>';
        echo '<div class="container">';
        echo '<h1>Application Error</h1>';
        
        echo '<div class="exception-info">';
        echo '<p><strong>Exception:</strong> ' . htmlspecialchars(get_class($e)) . '</p>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
        echo '</div>';
        
        echo '<h2>Stack Trace</h2>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        
        // Show previous exception if exists
        if ($previous = $e->getPrevious()) {
            echo '<div class="previous-exception">';
            echo '<h2>Previous Exception</h2>';
            echo '<div class="exception-info">';
            echo '<p><strong>Exception:</strong> ' . htmlspecialchars(get_class($previous)) . '</p>';
            echo '<p><strong>Message:</strong> ' . htmlspecialchars($previous->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($previous->getFile()) . '</p>';
            echo '<p><strong>Line:</strong> ' . $previous->getLine() . '</p>';
            echo '</div>';
            echo '<pre>' . htmlspecialchars($previous->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</body></html>';
    }

    /**
     * Render simple error page for production.
     *
     * @return void
     */
    protected function renderSimpleError(): void
    {
        echo '<!DOCTYPE html>';
        echo '<html><head><title>Internal Server Error</title><meta charset="utf-8">';
        echo '<style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f8f9fa;}</style>';
        echo '</head><body>';
        echo '<h1>Internal Server Error</h1>';
        echo '<p>The server encountered an error and could not complete your request.</p>';
        echo '<p>Please try again later.</p>';
        echo '</body></html>';
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
