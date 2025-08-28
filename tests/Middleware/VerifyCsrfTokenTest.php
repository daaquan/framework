<?php

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

    $this->request = $this->createMock(\Phalcon\Http\Request::class);
    $this->response = $this->createMock(\Phalcon\Http\Response::class);
});

it('allows GET requests without token', function () {
    $this->request->method('getMethod')->willReturn('GET');

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($this->request, $next);

    expect($result)->toBe($this->response);
});

it('allows HEAD requests without token', function () {
    $this->request->method('getMethod')->willReturn('HEAD');

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($this->request, $next);

    expect($result)->toBe($this->response);
});

it('allows OPTIONS requests without token', function () {
    $this->request->method('getMethod')->willReturn('OPTIONS');

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($this->request, $next);

    expect($result)->toBe($this->response);
});

it('validates CSRF token for POST requests', function () {
    $token = $this->csrf->generateToken();

    $this->request->method('getMethod')->willReturn('POST');
    $this->request->method('get')->with('_token', 'string')->willReturn($token);
    $this->request->method('getHeader')->willReturn(null);

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($this->request, $next);

    expect($result)->toBe($this->response);
});

it('throws exception for invalid CSRF token', function () {
    $this->csrf->generateToken();

    $this->request->method('getMethod')->willReturn('POST');
    $this->request->method('get')->with('_token', 'string')->willReturn('invalid-token');
    $this->request->method('getHeader')->willReturn(null);

    $next = fn ($req) => $this->response;

    expect(fn () => $this->middleware->handle($this->request, $next))
        ->toThrow(TokenMismatchException::class);
});

it('throws exception when no CSRF token provided', function () {
    $this->csrf->generateToken();

    $this->request->method('getMethod')->willReturn('POST');
    $this->request->method('get')->with('_token', 'string')->willReturn(null);
    $this->request->method('getHeader')->willReturn(null);

    $next = fn ($req) => $this->response;

    expect(fn () => $this->middleware->handle($this->request, $next))
        ->toThrow(TokenMismatchException::class);
});

it('accepts token from X-CSRF-TOKEN header', function () {
    $token = $this->csrf->generateToken();

    $this->request->method('getMethod')->willReturn('POST');
    $this->request->method('get')->with('_token', 'string')->willReturn(null);
    $this->request->method('getHeader')
        ->willReturnMap([
            ['X-CSRF-TOKEN', $token],
            ['X-XSRF-TOKEN', null],
        ]);

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($this->request, $next);

    expect($result)->toBe($this->response);
});

it('accepts token from X-XSRF-TOKEN header', function () {
    $token = $this->csrf->generateToken();

    $this->request->method('getMethod')->willReturn('POST');
    $this->request->method('get')->with('_token', 'string')->willReturn(null);
    $this->request->method('getHeader')
        ->willReturnMap([
            ['X-CSRF-TOKEN', null],
            ['X-XSRF-TOKEN', $token],
        ]);

    $next = fn ($req) => $this->response;
    $result = $this->middleware->handle($this->request, $next);

    expect($result)->toBe($this->response);
});

it('skips validation for excepted routes', function () {
    $middleware = $this->middleware->addExcept(['/api/webhook']);

    $this->request->method('getMethod')->willReturn('POST');
    $this->request->method('getURI')->willReturn('/api/webhook');

    $next = fn ($req) => $this->response;
    $result = $middleware->handle($this->request, $next);

    expect($result)->toBe($this->response);
});

it('supports wildcard patterns in except array', function () {
    $middleware = $this->middleware->addExcept(['/api/*']);

    $this->request->method('getMethod')->willReturn('POST');
    $this->request->method('getURI')->willReturn('/api/webhook/github');

    $next = fn ($req) => $this->response;
    $result = $middleware->handle($this->request, $next);

    expect($result)->toBe($this->response);
});

it('validates tokens for non-excepted routes', function () {
    $this->csrf->generateToken();
    $middleware = $this->middleware->addExcept(['/api/webhook']);

    $this->request->method('getMethod')->willReturn('POST');
    $this->request->method('getURI')->willReturn('/admin/users');
    $this->request->method('get')->with('_token', 'string')->willReturn('invalid');
    $this->request->method('getHeader')->willReturn(null);

    $next = fn ($req) => $this->response;

    expect(fn () => $middleware->handle($this->request, $next))
        ->toThrow(TokenMismatchException::class);
});
