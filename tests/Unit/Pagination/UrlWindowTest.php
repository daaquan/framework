<?php

use Phare\Pagination\LengthAwarePaginator;
use Phare\Pagination\UrlWindow;

it('can determine if there are any pages to show', function () {
    $paginator = new LengthAwarePaginator(['item1', 'item2', 'item3', 'item4'], 4, 2, 2);
    $window = new UrlWindow($paginator);

    expect($window->hasPages())->toBeTrue();
});

it('can get a url range for small number of urls', function () {
    $paginator = new LengthAwarePaginator(['item1', 'item2', 'item3', 'item4'], 4, 2, 2);
    $window = new UrlWindow($paginator);

    expect($window->get())->toBe([
        'first' => [1 => '/?page=1', 2 => '/?page=2'],
        'slider' => null,
        'last' => null,
    ]);
});

it('can get a url range for a window of links', function () {
    $array = [];
    for ($i = 1; $i <= 20; $i++) {
        $array[$i] = 'item' . $i;
    }

    $paginator = new LengthAwarePaginator($array, count($array), 1, 12);
    $window = new UrlWindow($paginator);

    $slider = [];
    for ($i = 9; $i <= 15; $i++) {
        $slider[$i] = '/?page=' . $i;
    }

    expect($window->get())->toBe([
        'first' => [1 => '/?page=1', 2 => '/?page=2'],
        'slider' => $slider,
        'last' => [19 => '/?page=19', 20 => '/?page=20'],
    ]);
});

it('supports custom onEachSide value for url windows', function () {
    $array = [];
    for ($i = 1; $i <= 20; $i++) {
        $array[$i] = 'item' . $i;
    }

    $paginator = new LengthAwarePaginator($array, count($array), 1, 8);
    $paginator->onEachSide(1);
    $window = new UrlWindow($paginator);

    $slider = [];
    for ($i = 7; $i <= 9; $i++) {
        $slider[$i] = '/?page=' . $i;
    }

    expect($window->get())->toBe([
        'first' => [1 => '/?page=1', 2 => '/?page=2'],
        'slider' => $slider,
        'last' => [19 => '/?page=19', 20 => '/?page=20'],
    ]);
});
