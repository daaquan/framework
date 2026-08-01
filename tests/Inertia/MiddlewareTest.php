<?php

use Phare\Http\Request;
use Phare\Http\Response as HttpResponse;
use Phare\Inertia\Middleware;
use Phare\Inertia\ResponseFactory;

// inertiaMwRequest() writes to $_SERVER; restore it so the leaked REQUEST_URI does not
// change what Paginator::resolveCurrentPath() returns in later test files.
$serverSnapshot = $_SERVER;
afterEach(function () use ($serverSnapshot) {
    $_SERVER = $serverSnapshot;
});

function inertiaMwRequest(array $headers = [], string $method = 'GET', string $uri = '/dash'): Request
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_HOST'] = 'example.test';

    foreach (['HTTP_X_INERTIA', 'HTTP_X_INERTIA_VERSION'] as $key) {
        unset($_SERVER[$key]);
    }
    foreach ($headers as $name => $value) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return new Request();
}

function inertiaMiddleware(string $version = 'v1'): Middleware
{
    $factory = new ResponseFactory();
    $factory->version($version);

    return new Middleware($factory);
}

it('responds 409 with X-Inertia-Location when the asset version is stale', function () {
    $called = false;
    $request = inertiaMwRequest(['X-Inertia' => 'true', 'X-Inertia-Version' => 'old'], 'GET');

    $response = inertiaMiddleware('v1')->handle($request, function () use (&$called) {
        $called = true;

        return new HttpResponse();
    });

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getHeaders()->get('X-Inertia-Location'))->toContain('/dash')
        ->and($called)->toBeFalse();
});

it('passes through when the asset version matches', function () {
    $request = inertiaMwRequest(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'], 'GET');

    $passed = (new HttpResponse())->status(200);
    $response = inertiaMiddleware('v1')->handle($request, fn () => $passed);

    expect($response)->toBe($passed);
});

it('coerces a redirect to 303 for PUT/PATCH/DELETE requests', function () {
    $request = inertiaMwRequest(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'], 'PUT');

    $response = inertiaMiddleware('v1')->handle($request, function () {
        return (new HttpResponse())->status(302)->header('Location', '/dash');
    });

    expect($response->getStatusCode())->toBe(303);
});

it('leaves a redirect untouched for GET requests', function () {
    $request = inertiaMwRequest(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'], 'GET');

    $response = inertiaMiddleware('v1')->handle($request, function () {
        return (new HttpResponse())->status(302)->header('Location', '/dash');
    });

    expect($response->getStatusCode())->toBe(302);
});
