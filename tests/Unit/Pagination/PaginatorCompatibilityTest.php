<?php

use Phare\Pagination\LengthAwarePaginator;
use Phare\Pagination\Paginator;

beforeEach(function () {
    $this->originalGet = $_GET;
    $this->originalServer = $_SERVER;

    Paginator::queryStringResolver(fn () => []);
    Paginator::currentPageResolver(fn (string $pageName = 'page') => $_GET[$pageName] ?? 1);
    Paginator::currentPathResolver(fn () => isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/');
});

afterEach(function () {
    $_GET = $this->originalGet;
    $_SERVER = $this->originalServer;

    Paginator::queryStringResolver(fn () => []);
    Paginator::currentPageResolver(fn (string $pageName = 'page') => $_GET[$pageName] ?? 1);
    Paginator::currentPathResolver(fn () => isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/');
});

it('builds pagination urls correctly when path already has query string', function () {
    $paginator = new Paginator(['a', 'b', 'c'], 2, 1, ['path' => '/users?sort=votes']);

    expect($paginator->url(2))->toBe('/users?sort=votes&page=2');
});

it('supports appends with null value', function () {
    $paginator = new Paginator(['a', 'b'], 2, 1, ['path' => '/users']);

    expect($paginator->appends(null))->toBe($paginator);
    expect($paginator->url(2))->toBe('/users?page=2');
});

it('supports with query string resolver', function () {
    Paginator::queryStringResolver(fn () => ['filter' => 'active', 'page' => 99]);

    $paginator = new Paginator(['a', 'b', 'c'], 2, 1, ['path' => '/users']);

    $paginator->withQueryString();

    expect($paginator->url(2))->toBe('/users?filter=active&page=2');
});

it('uses only the first per-page items and keeps has-more state', function () {
    $paginator = new Paginator(['item1', 'item2', 'item3'], 2, 1, ['path' => '/users']);

    expect($paginator->items()->toArray())->toBe(['item1', 'item2']);
    expect($paginator->hasMorePages())->toBeTrue();
});

it('resolves current page using custom pageName option', function () {
    $_GET = ['users_page' => '3'];
    $_SERVER['REQUEST_URI'] = '/users';

    $paginator = new Paginator(['a', 'b', 'c'], 2, null, ['path' => '/users', 'pageName' => 'users_page']);

    expect($paginator->currentPage())->toBe(3);
});

it('length aware paginator resolves current page using custom pageName option', function () {
    $_GET = ['posts_page' => '4'];
    $_SERVER['REQUEST_URI'] = '/posts';

    $paginator = new LengthAwarePaginator(['a', 'b'], 10, 2, null, ['path' => '/posts', 'pageName' => 'posts_page']);

    expect($paginator->currentPage())->toBe(4);
});
