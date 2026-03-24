<?php

use Phare\Pagination\Cursor;
use Phare\Pagination\CursorPaginator;

it('returns relevant context information', function () {
    $paginator = new CursorPaginator([['id' => 1], ['id' => 2], ['id' => 3]], 2, null, [
        'parameters' => ['id'],
    ]);

    expect($paginator->hasPages())->toBeTrue();
    expect($paginator->hasMorePages())->toBeTrue();
    expect($paginator->items())->toBe([['id' => 1], ['id' => 2]]);

    $nextCursor = (new Cursor(['id' => 2]))->encode();

    expect($paginator->toArray())->toBe([
        'data' => [['id' => 1], ['id' => 2]],
        'path' => '/',
        'per_page' => 2,
        'next_cursor' => $nextCursor,
        'next_page_url' => '/?cursor=' . $nextCursor,
        'prev_cursor' => null,
        'prev_page_url' => null,
    ]);
});

it('removes trailing slashes in path', function () {
    $paginator = new CursorPaginator([['id' => 4], ['id' => 5], ['id' => 6]], 2, null, [
        'path' => 'http://website.com/test/',
        'parameters' => ['id'],
    ]);

    $nextCursor = (new Cursor(['id' => 5]))->encode();

    expect($paginator->nextPageUrl())->toBe('http://website.com/test?cursor=' . $nextCursor);
});

it('can transform paginator items', function () {
    $paginator = new CursorPaginator([['id' => 4], ['id' => 5], ['id' => 6]], 2, null, [
        'path' => 'http://website.com/test',
        'parameters' => ['id'],
    ]);

    $paginator->through(function ($item) {
        $item['id'] += 2;

        return $item;
    });

    expect($paginator)->toBeInstanceOf(CursorPaginator::class);
    expect($paginator->items())->toBe([['id' => 6], ['id' => 7]]);
});

it('handles first and last page state', function () {
    $first = new CursorPaginator([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]], 2, null, [
        'parameters' => ['id'],
    ]);

    expect($first->onFirstPage())->toBeTrue();
    expect($first->onLastPage())->toBeFalse();

    $cursor = new Cursor(['id' => 3]);
    $last = new CursorPaginator([['id' => 3], ['id' => 4]], 2, $cursor, [
        'parameters' => ['id'],
    ]);

    expect($last->onFirstPage())->toBeFalse();
    expect($last->onLastPage())->toBeTrue();
});

it('returns empty cursors when items are empty', function () {
    $cursor = new Cursor(['id' => 25], true);

    $paginator = new CursorPaginator([], 25, $cursor, [
        'path' => 'http://website.com/test',
        'cursorName' => 'cursor',
        'parameters' => ['id'],
    ]);

    expect($paginator->toArray())->toBe([
        'data' => [],
        'path' => 'http://website.com/test',
        'per_page' => 25,
        'next_cursor' => null,
        'next_page_url' => null,
        'prev_cursor' => null,
        'prev_page_url' => null,
    ]);
});

it('supports json serialization methods', function () {
    $paginator = new CursorPaginator([['id' => 1], ['id' => 2], ['id' => 3]], 2, null, [
        'parameters' => ['id'],
    ]);

    $expected = json_encode($paginator->toArray());

    expect($paginator->toJson())->toBe($expected);
    expect($paginator->toPrettyJson())->toContain("\n");
});
