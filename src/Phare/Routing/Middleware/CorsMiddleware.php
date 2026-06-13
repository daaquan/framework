<?php

namespace Phare\Routing\Middleware;

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Http\MiddlewareContract;
use Phare\Foundation\Http\Concerns\AfterMiddleware;

class CorsMiddleware extends MiddlewareContract implements AfterMiddleware
{
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        $response = $next($request);

        $origin = $request->getHeader('Origin');
        $allowedOrigins = $this->allowedOrigins();
        $allowsAny = in_array('*', $allowedOrigins, true);

        if ($allowsAny) {
            // Wildcard origins cannot be combined with credentials.
            $response->setHeader('Access-Control-Allow-Origin', '*');
        } elseif ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            // Echo the request origin only when explicitly vetted.
            $response
                ->setHeader('Access-Control-Allow-Origin', $origin)
                ->setHeader('Vary', 'Origin')
                ->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        $response
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Range, Content-Disposition, Content-Type, Authorization')
            ->setHeader('Access-Control-Max-Age', '86400');

        // Short-circuit preflight requests with an empty success response.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response->setStatusCode(204, 'No Content')->setContent('');
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    protected function allowedOrigins(): array
    {
        $origins = config('cors.allowed_origins', ['*']);

        if (is_string($origins)) {
            $origins = [$origins];
        }

        return is_array($origins) && $origins !== [] ? array_values($origins) : ['*'];
    }
}
