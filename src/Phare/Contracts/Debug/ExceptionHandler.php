<?php

namespace Phare\Contracts\Debug;

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
     * @param \Phare\Http\Request $request
     * @return \Phare\Http\Response
     *
     * @throws \Throwable
     */
    public function render($request, \Throwable $e);

    /**
     * Render an exception to the console.
     *
     * @return void
     */
    public function renderForConsole(\Phare\Console\Output\Output $output, \Throwable $e);
}
