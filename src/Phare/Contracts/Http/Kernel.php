<?php

namespace Phare\Contracts\Http;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Foundation\Application;

interface Kernel
{
    /**
     * Bootstrap the application for HTTP requests.
     *
     * @return void
     */
    public function bootstrap();

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(RequestInterface $request): ResponseInterface;

    /**
     * Perform any final actions for the request lifecycle.
     */
    public function terminate(RequestInterface $request, ResponseInterface $response): void;

    /**
     * Get the application instance.
     */
    public function getApplication(): Application;
}
