<?php

use Phare\Collections\Collection;

class PluckPlainObject
{
    public function __construct(public string $name, public ?int $age = null) {}
}

class PluckMagicObject
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        // Deliberately strict, the way Eloquent models are: a null value
        // reports as not set. pluck must not rely on this.
        return isset($this->data[$key]);
    }
}

it('plucks values from arrays', function () {
    expect(Collection::make([['name' => 'ann'], ['name' => 'bob']])->pluck('name')->toArray())
        ->toBe(['ann', 'bob']);
});

it('keeps null values but skips array items missing the key', function () {
    expect(Collection::make([['name' => null], ['name' => 'bob'], ['other' => 'x']])->pluck('name')->toArray())
        ->toBe([null, 'bob']);
});

it('plucks public properties from plain objects', function () {
    expect(Collection::make([new PluckPlainObject('ann'), new PluckPlainObject('bob')])->pluck('name')->toArray())
        ->toBe(['ann', 'bob']);
});

it('plucks through __get on objects that store their data privately', function () {
    $items = [new PluckMagicObject(['name' => 'ann']), new PluckMagicObject(['name' => 'bob'])];

    expect(Collection::make($items)->pluck('name')->toArray())->toBe(['ann', 'bob']);
});

it('returns null rather than dropping an object whose key resolves to null', function () {
    // Dropping would misalign the result with the source collection.
    $items = [new PluckMagicObject(['name' => 'ann']), new PluckMagicObject([])];

    expect(Collection::make($items)->pluck('name')->toArray())->toBe(['ann', null]);
});

it('keys the result by a second attribute', function () {
    $items = [new PluckMagicObject(['id' => 7, 'name' => 'ann']), new PluckMagicObject(['id' => 9, 'name' => 'bob'])];

    expect(Collection::make($items)->pluck('name', 'id')->toArray())->toBe([7 => 'ann', 9 => 'bob']);
});

it('keys arrays by a second attribute', function () {
    expect(Collection::make([['id' => 1, 'name' => 'ann']])->pluck('name', 'id')->toArray())
        ->toBe([1 => 'ann']);
});
