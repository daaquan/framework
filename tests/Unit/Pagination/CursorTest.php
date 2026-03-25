<?php

use Phare\Pagination\Cursor;

it('can encode and decode cursor successfully', function () {
    $cursor = new Cursor([
        'id' => 422,
        'created_at' => '2026-03-25 12:00:00',
    ], true);

    $decoded = Cursor::fromEncoded($cursor->encode());

    expect($decoded)->toBeInstanceOf(Cursor::class);
    expect($decoded?->toArray())->toBe($cursor->toArray());
});

it('can get cursor params', function () {
    $cursor = new Cursor([
        'id' => 422,
        'created_at' => '2026-03-25 12:00:00',
    ], true);

    expect($cursor->parameters(['created_at', 'id']))->toBe(['2026-03-25 12:00:00', 422]);
});

it('can get single cursor param', function () {
    $cursor = new Cursor([
        'id' => 422,
        'created_at' => '2026-03-25 12:00:00',
    ], true);

    expect($cursor->parameter('created_at'))->toBe('2026-03-25 12:00:00');
});
