<?php

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Contracts\Http\Middleware;
use Phare\Contracts\Http\MiddlewareContract;
use Phare\Foundation\Http\Concerns\BeforeMiddleware;

beforeEach(function () {
    $this->request = Mockery::mock(RequestInterface::class);
    $this->response = Mockery::mock(ResponseInterface::class);
});

test('MiddlewareContract does not extend Di', function () {
    $reflection = new ReflectionClass(MiddlewareContract::class);
    expect($reflection->getParentClass())->toBeFalse();
});

test('MiddlewareContract implements Middleware interface', function () {
    $reflection = new ReflectionClass(MiddlewareContract::class);
    expect($reflection->implementsInterface(Middleware::class))->toBeTrue();
});

test('concrete middleware handle method receives request and closure next', function () {
    $middleware = new class() extends MiddlewareContract implements BeforeMiddleware
    {
        public function handle(RequestInterface $request, Closure $next): ResponseInterface
        {
            return $next($request);
        }
    };

    $result = $middleware->handle($this->request, fn () => $this->response);

    expect($result)->toBe($this->response);
});
