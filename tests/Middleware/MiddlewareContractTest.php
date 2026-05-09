<?php

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Http\Middleware;
use Phare\Contracts\Http\MiddlewareContract;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;

test('MiddlewareContract does not extend Di', function () {
    $reflection = new ReflectionClass(MiddlewareContract::class);
    expect($reflection->getParentClass())->toBeFalse();
});

test('MiddlewareContract implements Middleware interface', function () {
    $reflection = new ReflectionClass(MiddlewareContract::class);
    expect($reflection->implementsInterface(Middleware::class))->toBeTrue();
});

test('concrete middleware handle method receives request and closure next', function () {
    // Use PHPUnit's createMock() instead of Mockery to avoid the PHP 8.4 deprecation
    // that Mockery triggers when generating a proxy for Phalcon\Http\RequestInterface::get()
    // (implicitly nullable parameter in the Phalcon interface definition).
    $request = $this->createMock(RequestInterface::class);
    $response = $this->createMock(ResponseInterface::class);

    $middleware = new class() extends MiddlewareContract implements BeforeMiddleware
    {
        public function handle(RequestInterface $request, Closure $next): ResponseInterface
        {
            return $next($request);
        }
    };

    $result = $middleware->handle($request, fn () => $response);

    expect($result)->toBe($response);
});
