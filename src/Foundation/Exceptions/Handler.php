<?php

namespace Phare\Foundation\Exceptions;

use Phare\Collections\Arr;
use Phare\Console\Output\Output;
use Phare\Container\Container;
use Phare\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Phare\Http\Response;
use Phare\Support\Facades\Log;
use Psr\Log\LogLevel;

class Handler implements ExceptionHandlerContract
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var string[]
     */
    protected $dontReport = [];

    /**
     * A map of exceptions with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [];

    /**
     * The registered exception mappings.
     *
     * @var array<string, \Closure>
     */
    protected $exceptionMap = [];

    /**
     * A list of the internal exception types that should not be reported.
     *
     * @var string[]
     */
    protected $internalDontReport = [];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var string[]
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function __construct(protected Container $container)
    {
        $this->register();
    }

    public function register(): void
    {
        //
    }

    /**
     * Set the log level for the given exception type.
     *
     * @param class-string<\Throwable> $type
     * @param \Psr\Log\LogLevel::* $level
     * @return $this
     */
    public function level($type, $level)
    {
        $this->levels[$type] = $level;

        return $this;
    }

    public function report(\Throwable $e)
    {
        if (!$this->shouldReport($e)) {
            return;
        }

        $this->reportThrowable($e);
    }

    protected function reportThrowable(\Throwable $e)
    {
        $context = $this->buildExceptionContext($e);

        //        $errorMessage = [
        //            'message' => $e->getMessage(),
        //            'file' => $e->getFile(),
        //            'line' => $e->getLine(),
        //            'trace' => $e->getTrace(),
        //            'context' => $context,
        //        ];
        $errorMessage = sprintf('%s in %s on line %s', $e->getMessage(), $e->getFile(), $e->getLine());

        $level = Arr::first($this->levels, static fn ($level, $type) => $e instanceof $type) ?: LogLevel::ERROR;

        try {
            /** @var Log $logger */
            $logger = $this->container->make('log');
            $logger::log($level, $errorMessage, $context);
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * Create the context array for logging the given exception.
     *
     * @return array
     */
    protected function buildExceptionContext(\Throwable $e)
    {
        return array_merge(
            $this->exceptionContext($e),
            $this->context(),
            ['exception' => $e]
        );
    }

    /**
     * Get the default exception context variables for logging.
     *
     * @return array
     */
    protected function exceptionContext(\Throwable $e)
    {
        if (method_exists($e, 'context')) {
            return $e->context();
        }

        return [];
    }

    /**
     * Get the default context variables for logging.
     *
     * @return array
     */
    protected function context()
    {
        return [];
    }

    public function shouldReport(\Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return false;
            }
        }

        foreach ($this->internalDontReport as $type) {
            if ($e instanceof $type) {
                return false;
            }
        }

        return true;
    }

    public function render($request, \Throwable $e)
    {
        return new Response(null, 500);
    }

    public function renderForConsole(Output $output, \Throwable $e)
    {
        $output->writeError($e->getMessage());
    }
}
