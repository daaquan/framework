<?php

namespace Phare\Contracts\Debug;

use Phare\Console\Output\Output;
use Phare\Http\Request;
use Phare\Http\Response;

interface ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function report(\Throwable $e);

    /**
     * Determine if the exception should be reported.
     *
     * @return bool
     */
    public function shouldReport(\Throwable $e);

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @return Response
     *
     * @throws \Throwable
     */
    public function render($request, \Throwable $e);

    /**
     * Render an exception to the console.
     *
     * @return void
     */
    public function renderForConsole(Output $output, \Throwable $e);
}
