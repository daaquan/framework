<?php

namespace Phare\Inertia;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Http\Middleware as MiddlewareContract;
use Phare\Http\Response as HttpResponse;

/**
 * Handles the parts of the Inertia protocol that wrap an ordinary response:
 *  - asset version mismatches trigger a hard reload (409 + X-Inertia-Location)
 *  - redirects after PUT/PATCH/DELETE are coerced to 303 so the browser GETs
 *
 * Apps subclass this to set their asset version and shared props.
 */
class Middleware implements MiddlewareContract
{
    public function __construct(protected ResponseFactory $inertia) {}

    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        if ($this->isInertia($request) && $request->getMethod() === 'GET' && $this->versionChanged($request)) {
            return $this->onVersionChange($request);
        }

        $response = $next($request);

        if ($this->isInertia($request) && $this->isRedirect($response) && $this->shouldForceSeeOther($request)) {
            $response->setStatusCode(303);
        }

        return $response;
    }

    protected function isInertia(RequestInterface $request): bool
    {
        return (bool)$request->getHeader('X-Inertia');
    }

    protected function versionChanged(RequestInterface $request): bool
    {
        return (string)$request->getHeader('X-Inertia-Version') !== (string)$this->inertia->getVersion();
    }

    protected function onVersionChange(RequestInterface $request): HttpResponse
    {
        $url = method_exists($request, 'fullUrl') ? $request->fullUrl() : ($_SERVER['REQUEST_URI'] ?? '/');

        return (new HttpResponse())->status(409)->header('X-Inertia-Location', $url);
    }

    protected function isRedirect(ResponseInterface $response): bool
    {
        $status = (int)$response->getStatusCode();

        return $status >= 300 && $status < 400;
    }

    protected function shouldForceSeeOther(RequestInterface $request): bool
    {
        return in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'], true);
    }
}
