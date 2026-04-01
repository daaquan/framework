<?php

use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phare\Middleware\TokenMismatchException;
use Phare\Middleware\VerifyCsrfToken;
use Phare\Security\Csrf;
use Tests\Support\SimpleApplication;
use Tests\Support\SimpleSessionStore;

beforeEach(function () {
    $this->app = new SimpleApplication();
    $this->app->singleton('session', function () {
        return new SimpleSessionStore();
    });

    $this->csrf = new Csrf($this->app);
    $this->app->singleton(Csrf::class, fn () => $this->csrf);

    $this->middleware = new VerifyCsrfToken($this->app);
    // Use PHPUnit createMock() to avoid the PHP 8.4 deprecation that Mockery triggers
    // when generating a proxy for Phalcon\Http\ResponseInterface::redirect()
    // (implicitly nullable parameter in the Phalcon interface definition).
    $this->response = $this->createMock(ResponseInterface::class);
});

/**
 * Build a PHPUnit stub for RequestInterface to avoid the PHP 8.4 deprecation that
 * Mockery triggers when proxying Phalcon\Http\RequestInterface::get() (implicitly
 * nullable parameter).
 */
function fakeRequest(
    string $method = 'GET',
    string $uri = '/',
    array $input = [],
    array $headers = []
): RequestInterface {
    // PHPUnit's getMockBuilder() generates explicit-nullable parameters in PHP 8.4+.
    $mock = (new \PHPUnit\Framework\MockObject\MockBuilder(
        new class('_fakeRequest') extends \PHPUnit\Framework\TestCase {
            public function runTest(): void {}
        },
        RequestInterface::class
    ))->getMock();

    $mock->method('getMethod')->willReturn($method);
    $mock->method('getURI')->willReturn($uri);

    $mock->method('get')->willReturnCallback(function ($key) use ($input) {
        return $input[$key] ?? null;
    });

    $mock->method('getHeader')->willReturnCallback(function ($name) use ($headers) {
        return $headers[$name] ?? '';
    });

    return $mock;
}

it('allows GET requests without token', function () {
    $request = fakeRequest(method: 'GET');

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($request, $next);

    expect($result)->toBe($this->response);
});

it('allows HEAD requests without token', function () {
    $request = fakeRequest(method: 'HEAD');

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($request, $next);

    expect($result)->toBe($this->response);
});

it('allows OPTIONS requests without token', function () {
    $request = fakeRequest(method: 'OPTIONS');

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($request, $next);

    expect($result)->toBe($this->response);
});

it('validates CSRF token for POST requests', function () {
    $token = $this->csrf->generateToken();
    $request = fakeRequest(method: 'POST', input: ['_token' => $token]);

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($request, $next);

    expect($result)->toBe($this->response);
});

it('throws exception for invalid CSRF token', function () {
    $this->csrf->generateToken();
    $request = fakeRequest(method: 'POST', input: ['_token' => 'invalid-token']);

    $next = fn ($req) => $this->response;

    expect(fn () => $this->middleware->handle($request, $next))
        ->toThrow(TokenMismatchException::class);
});

it('throws exception when no CSRF token provided', function () {
    $this->csrf->generateToken();
    $request = fakeRequest(method: 'POST');

    $next = fn ($req) => $this->response;

    expect(fn () => $this->middleware->handle($request, $next))
        ->toThrow(TokenMismatchException::class);
});

it('accepts token from X-CSRF-TOKEN header', function () {
    $token = $this->csrf->generateToken();
    $request = fakeRequest(method: 'POST', headers: [
        'X-CSRF-TOKEN' => $token,
    ]);

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($request, $next);

    expect($result)->toBe($this->response);
});

it('accepts token from X-XSRF-TOKEN header', function () {
    $token = $this->csrf->generateToken();
    $request = fakeRequest(method: 'POST', headers: [
        'X-XSRF-TOKEN' => $token,
    ]);

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($request, $next);

    expect($result)->toBe($this->response);
});

it('skips validation for excepted routes', function () {
    $middleware = $this->middleware->addExcept(['/api/webhook']);
    $request = fakeRequest(method: 'POST', uri: '/api/webhook');

    $next = fn ($req) => $this->response;
    $result = $middleware->handle($request, $next);

    expect($result)->toBe($this->response);
});

it('supports wildcard patterns in except array', function () {
    $middleware = $this->middleware->addExcept(['/api/*']);
    $request = fakeRequest(method: 'POST', uri: '/api/webhook/github');

    $next = fn ($req) => $this->response;
    $result = $middleware->handle($request, $next);

    expect($result)->toBe($this->response);
});

it('validates tokens for non-excepted routes', function () {
    $this->csrf->generateToken();
    $middleware = $this->middleware->addExcept(['/api/webhook']);
    $request = fakeRequest(method: 'POST', uri: '/admin/users', input: ['_token' => 'invalid']);

    $next = fn ($req) => $this->response;

    expect(fn () => $middleware->handle($request, $next))
        ->toThrow(TokenMismatchException::class);
});
