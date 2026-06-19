<?php

use Phare\Http\Request;
use Phare\Inertia\LazyProp;
use Phare\Inertia\Response;
use Phare\Inertia\ResponseFactory;

/**
 * Build a Phare/Phalcon Request from explicit headers, since Phalcon reads
 * everything from the $_SERVER superglobal.
 */
function inertiaRequest(array $headers = [], string $method = 'GET', string $uri = '/dash'): Request
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_HOST'] = 'example.test';

    foreach (['HTTP_X_INERTIA', 'HTTP_X_INERTIA_VERSION', 'HTTP_X_INERTIA_PARTIAL_DATA', 'HTTP_X_INERTIA_PARTIAL_COMPONENT'] as $key) {
        unset($_SERVER[$key]);
    }
    foreach ($headers as $name => $value) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return new Request();
}

function stubRenderer(): callable
{
    // Mimic the @inertia directive: emit the root element from the page array.
    return fn (string $view, array $data) => ResponseFactory::renderRootElement($data['page']);
}

it('returns a JSON response when the X-Inertia header is present', function () {
    $response = new Response('Dashboard', ['user' => 'Ada'], 'app', 'v1');
    $request = inertiaRequest(['X-Inertia' => 'true']);

    $http = $response->toResponse($request);
    $page = json_decode($http->getContent(), true);

    expect($page['component'])->toBe('Dashboard')
        ->and($page['props'])->toBe(['user' => 'Ada'])
        ->and($page['version'])->toBe('v1')
        ->and($page['url'])->toContain('/dash')
        ->and($http->getHeaders()->get('X-Inertia'))->toBe('true')
        ->and($http->getHeaders()->get('Vary'))->toBe('X-Inertia');
});

it('returns an HTML root element when X-Inertia header is absent', function () {
    $response = (new Response('Dashboard', ['user' => 'Ada'], 'app', 'v1'))
        ->setViewRenderer(stubRenderer());
    $request = inertiaRequest();

    $html = $response->toResponse($request)->getContent();

    expect($html)->toContain('<div id="app"></div>')
        ->and($html)->toContain('<script type="application/json" data-page="app">');
});

it('keeps only allowlisted props on a matching partial reload', function () {
    $response = new Response('Dashboard', ['a' => 1, 'b' => 2, 'c' => 3], 'app', 'v1');
    $request = inertiaRequest([
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'a,c',
    ]);

    $page = json_decode($response->toResponse($request)->getContent(), true);

    expect($page['props'])->toBe(['a' => 1, 'c' => 3]);
});

it('returns all props when the partial component does not match', function () {
    $response = new Response('Dashboard', ['a' => 1, 'b' => 2], 'app', 'v1');
    $request = inertiaRequest([
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Settings',
        'X-Inertia-Partial-Data' => 'a',
    ]);

    $page = json_decode($response->toResponse($request)->getContent(), true);

    expect($page['props'])->toBe(['a' => 1, 'b' => 2]);
});

it('excludes lazy props on a full visit but evaluates eager closures', function () {
    $response = new Response('Dashboard', [
        'eager' => fn () => 'always',
        'lazy' => new LazyProp(fn () => 'sometimes'),
    ], 'app', 'v1');
    $request = inertiaRequest(['X-Inertia' => 'true']);

    $page = json_decode($response->toResponse($request)->getContent(), true);

    expect($page['props'])->toBe(['eager' => 'always']);
});

it('includes a lazy prop only when requested in a partial reload', function () {
    $response = new Response('Dashboard', [
        'eager' => fn () => 'always',
        'lazy' => new LazyProp(fn () => 'sometimes'),
    ], 'app', 'v1');
    $request = inertiaRequest([
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'lazy',
    ]);

    $page = json_decode($response->toResponse($request)->getContent(), true);

    expect($page['props'])->toBe(['lazy' => 'sometimes']);
});
