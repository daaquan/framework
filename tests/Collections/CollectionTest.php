<?php

use Phare\Collections\Collection;
use Phare\Collections\Exceptions\ItemNotFoundException;
use Phare\Collections\Exceptions\MultipleItemsFoundException;

it('reject() keeps only items the callback returns false for', function () {
    $collection = new Collection([1, 2, 3, 4]);
    $result = $collection->reject(fn ($value) => $value % 2 === 0);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and(array_values($result->toArray()))->toBe([1, 3]);
});

it('reject() does not mutate the original collection', function () {
    $collection = new Collection([1, 2, 3]);
    $collection->reject(fn ($value) => $value > 1);

    expect($collection->toArray())->toBe([1, 2, 3]);
});

it('reduce() folds the collection into a single value', function () {
    $collection = new Collection([1, 2, 3, 4]);

    expect($collection->reduce(fn ($carry, $item) => $carry + $item, 0))->toBe(10);
});

it('reduce() uses the given initial value', function () {
    $collection = new Collection([1, 2]);

    expect($collection->reduce(fn ($carry, $item) => $carry + $item, 100))->toBe(103);
});

it('each() iterates over every item and returns the collection', function () {
    $collection = new Collection([1, 2, 3]);
    $seen = [];

    $result = $collection->each(function ($value) use (&$seen) {
        $seen[] = $value;
    });

    expect($seen)->toBe([1, 2, 3])
        ->and($result)->toBe($collection);
});

it('each() stops iterating when the callback returns false', function () {
    $collection = new Collection([1, 2, 3, 4]);
    $seen = [];

    $collection->each(function ($value) use (&$seen) {
        $seen[] = $value;

        return $value < 2 ? null : false;
    });

    expect($seen)->toBe([1, 2]);
});

it('every() returns true when all items satisfy the predicate', function () {
    $collection = new Collection([2, 4, 6]);

    expect($collection->every(fn ($value) => $value % 2 === 0))->toBeTrue();
});

it('every() returns false when any item fails the predicate', function () {
    $collection = new Collection([2, 3, 4]);

    expect($collection->every(fn ($value) => $value % 2 === 0))->toBeFalse();
});

it('reverse() reverses the items', function () {
    $collection = new Collection([1, 2, 3]);
    $result = $collection->reverse();

    expect($result)->toBeInstanceOf(Collection::class)
        ->and(array_values($result->toArray()))->toBe([3, 2, 1]);
});

it('reverse() does not mutate the original collection', function () {
    $collection = new Collection([1, 2, 3]);
    $collection->reverse();

    expect($collection->toArray())->toBe([1, 2, 3]);
});

it('concat() appends items from an array', function () {
    $collection = new Collection([1, 2]);
    $result = $collection->concat([3, 4]);

    expect(array_values($result->toArray()))->toBe([1, 2, 3, 4]);
});

it('concat() appends items from another collection', function () {
    $collection = new Collection([1, 2]);
    $result = $collection->concat(new Collection([3, 4]));

    expect(array_values($result->toArray()))->toBe([1, 2, 3, 4]);
});

it('collapse() flattens a collection of arrays one level deep', function () {
    $collection = new Collection([[1, 2], [3, 4], [5]]);
    $result = $collection->collapse();

    expect(array_values($result->toArray()))->toBe([1, 2, 3, 4, 5]);
});

it('nth() returns every nth element', function () {
    $collection = new Collection(['a', 'b', 'c', 'd', 'e', 'f']);

    expect(array_values($collection->nth(2)->toArray()))->toBe(['a', 'c', 'e']);
});

it('nth() honors the offset', function () {
    $collection = new Collection(['a', 'b', 'c', 'd', 'e', 'f']);

    expect(array_values($collection->nth(2, 1)->toArray()))->toBe(['b', 'd', 'f']);
});

it('tap() passes the collection to the callback and returns it', function () {
    $collection = new Collection([1, 2, 3]);
    $tapped = null;

    $result = $collection->tap(function ($collection) use (&$tapped) {
        $tapped = $collection;
    });

    expect($result)->toBe($collection)
        ->and($tapped)->toBe($collection);
});

it('pipe() returns the result of the callback', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->pipe(fn ($collection) => $collection->sum()))->toBe(6);
});

it('partition() splits the collection by a predicate', function () {
    $collection = new Collection([1, 2, 3, 4]);
    [$even, $odd] = $collection->partition(fn ($value) => $value % 2 === 0);

    expect(array_values($even->toArray()))->toBe([2, 4])
        ->and(array_values($odd->toArray()))->toBe([1, 3]);
});

it('partition() does not mutate the original collection', function () {
    $collection = new Collection([1, 2, 3]);
    $collection->partition(fn ($value) => $value > 1);

    expect($collection->toArray())->toBe([1, 2, 3]);
});

it('search() returns the key of a matching value', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->search('b'))->toBe(1);
});

it('search() returns false when the value is absent', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->search('z'))->toBeFalse();
});

it('search() accepts a predicate callback', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->search(fn ($value) => $value === 'c'))->toBe(2);
});

it('only() keeps just the given keys', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

    expect($collection->only('a', 'c')->toArray())->toBe(['a' => 1, 'c' => 3]);
});

it('only() accepts an array of keys', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

    expect($collection->only(['b'])->toArray())->toBe(['b' => 2]);
});

it('join() concatenates the items with a glue string', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->join(', '))->toBe('a, b, c');
});

it('join() uses a distinct final glue when given', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->join(', ', ' and '))->toBe('a, b and c');
});

it('join() returns a single item without any glue', function () {
    $collection = new Collection(['a']);

    expect($collection->join(', ', ' and '))->toBe('a');
});

it('join() returns an empty string for an empty collection', function () {
    $collection = new Collection([]);

    expect($collection->join(', '))->toBe('');
});

it('pad() pads the collection up to the given size', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->pad(5, 0)->toArray())->toBe([1, 2, 3, 0, 0]);
});

it('pad() pads to the left when the size is negative', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->pad(-5, 0)->toArray())->toBe([0, 0, 1, 2, 3]);
});

it('pad() is a no-op when the size is within the current count', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->pad(2, 0)->toArray())->toBe([1, 2, 3]);
});

it('where() filters array items by an attribute (equality)', function () {
    $users = new Collection([
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25],
        ['name' => 'Carol', 'age' => 30],
    ]);

    $result = $users->where('age', 30);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and(array_column($result->toArray(), 'name'))->toBe(['Alice', 'Carol']);
});

it('where() supports an explicit comparison operator', function () {
    $users = new Collection([
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25],
        ['name' => 'Carol', 'age' => 40],
    ]);

    $result = $users->where('age', '>', 26);

    expect(array_column($result->toArray(), 'name'))->toBe(['Alice', 'Carol']);
});

it('where() reads attributes from object items', function () {
    $items = new Collection([(object)['x' => 1], (object)['x' => 2], (object)['x' => 2]]);

    expect($items->where('x', 2)->count())->toBe(2);
});

it('where() does not mutate the original collection', function () {
    $users = new Collection([['age' => 1], ['age' => 2]]);
    $users->where('age', 1);

    expect($users->count())->toBe(2);
});

it('whereIn() keeps items whose attribute is in the set', function () {
    $users = new Collection([
        ['name' => 'Alice'],
        ['name' => 'Bob'],
        ['name' => 'Carol'],
    ]);

    $result = $users->whereIn('name', ['Bob', 'Carol']);

    expect(array_column($result->toArray(), 'name'))->toBe(['Bob', 'Carol']);
});

it('whereNotIn() removes items whose attribute is in the set', function () {
    $users = new Collection([
        ['name' => 'Alice'],
        ['name' => 'Bob'],
        ['name' => 'Carol'],
    ]);

    $result = $users->whereNotIn('name', ['Bob']);

    expect(array_column($result->toArray(), 'name'))->toBe(['Alice', 'Carol']);
});

it('firstWhere() returns the first matching item', function () {
    $users = new Collection([
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25],
    ]);

    expect($users->firstWhere('age', 25)['name'])->toBe('Bob');
});

it('firstWhere() returns null when nothing matches', function () {
    $users = new Collection([['name' => 'Alice']]);

    expect($users->firstWhere('name', 'Zed'))->toBeNull();
});

it('whenEmpty() runs the callback on an empty collection', function () {
    $collection = new Collection([]);
    $passed = null;

    $result = $collection->whenEmpty(function ($collection) use (&$passed) {
        $passed = $collection;
    });

    expect($passed)->toBe($collection)
        ->and($result)->toBe($collection);
});

it('whenEmpty() skips the callback on a non-empty collection', function () {
    $collection = new Collection([1]);
    $ran = false;

    $collection->whenEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeFalse();
});

it('whenEmpty() runs the default callback on a non-empty collection', function () {
    $collection = new Collection([1]);
    $which = null;

    $collection->whenEmpty(
        function () use (&$which) {
            $which = 'callback';
        },
        function () use (&$which) {
            $which = 'default';
        },
    );

    expect($which)->toBe('default');
});

it('whenNotEmpty() runs the callback on a non-empty collection', function () {
    $collection = new Collection([1]);
    $ran = false;

    $collection->whenNotEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('whenNotEmpty() skips the callback on an empty collection', function () {
    $collection = new Collection([]);
    $ran = false;

    $collection->whenNotEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeFalse();
});

it('unlessEmpty() runs the callback on a non-empty collection', function () {
    $collection = new Collection([1]);
    $ran = false;

    $collection->unlessEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('unlessNotEmpty() runs the callback on an empty collection', function () {
    $collection = new Collection([]);
    $ran = false;

    $collection->unlessNotEmpty(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('whereNull() keeps items whose attribute is null', function () {
    $users = new Collection([
        ['name' => 'Alice', 'deleted_at' => null],
        ['name' => 'Bob', 'deleted_at' => '2020'],
        ['name' => 'Carol', 'deleted_at' => null],
    ]);

    $result = $users->whereNull('deleted_at');

    expect(array_column($result->toArray(), 'name'))->toBe(['Alice', 'Carol']);
});

it('whereNotNull() keeps items whose attribute is not null', function () {
    $users = new Collection([
        ['name' => 'Alice', 'deleted_at' => null],
        ['name' => 'Bob', 'deleted_at' => '2020'],
    ]);

    expect(array_column($users->whereNotNull('deleted_at')->toArray(), 'name'))->toBe(['Bob']);
});

it('whereNull() without a key tests the items themselves', function () {
    $collection = new Collection([1, null, 2, null]);

    expect($collection->whereNull()->count())->toBe(2);
});

it('whereNotNull() without a key tests the items themselves', function () {
    $collection = new Collection([1, null, 2]);

    expect(array_values($collection->whereNotNull()->toArray()))->toBe([1, 2]);
});

it('before() returns the item preceding the given value', function () {
    $collection = new Collection(['a', 'b', 'c', 'd']);

    expect($collection->before('c'))->toBe('b');
});

it('before() returns null for the first item', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->before('a'))->toBeNull();
});

it('before() returns null when the value is absent', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->before('z'))->toBeNull();
});

it('after() returns the item following the given value', function () {
    $collection = new Collection(['a', 'b', 'c', 'd']);

    expect($collection->after('c'))->toBe('d');
});

it('after() returns null for the last item', function () {
    $collection = new Collection(['a', 'b', 'c']);

    expect($collection->after('c'))->toBeNull();
});

it('after() accepts a predicate callback', function () {
    $collection = new Collection(['a', 'b', 'c', 'd']);

    expect($collection->after(fn ($value) => $value === 'b'))->toBe('c');
});

it('make() creates a collection from an array', function () {
    expect(Collection::make([1, 2, 3])->toArray())->toBe([1, 2, 3]);
});

it('make() defaults to an empty collection', function () {
    expect(Collection::make()->toArray())->toBe([]);
});

it('make() unwraps an existing collection', function () {
    expect(Collection::make(new Collection([1, 2]))->toArray())->toBe([1, 2]);
});

it('times() builds a collection of sequential numbers', function () {
    expect(Collection::times(3)->toArray())->toBe([1, 2, 3]);
});

it('times() maps each number through the callback', function () {
    expect(Collection::times(3, fn ($n) => $n * 2)->toArray())->toBe([2, 4, 6]);
});

it('times() returns an empty collection for a non-positive count', function () {
    expect(Collection::times(0)->toArray())->toBe([]);
});

it('range() builds a collection over a numeric range', function () {
    expect(Collection::range(1, 5)->toArray())->toBe([1, 2, 3, 4, 5]);
});

it('range() honors the step', function () {
    expect(Collection::range(0, 10, 5)->toArray())->toBe([0, 5, 10]);
});

it('wrap() wraps a scalar into a collection', function () {
    expect(Collection::wrap('a')->toArray())->toBe(['a']);
});

it('wrap() leaves an array as-is', function () {
    expect(Collection::wrap([1, 2])->toArray())->toBe([1, 2]);
});

it('wrap() rewraps an existing collection', function () {
    expect(Collection::wrap(new Collection([1, 2]))->toArray())->toBe([1, 2]);
});

it('unwrap() extracts items from a collection', function () {
    expect(Collection::unwrap(new Collection([1, 2])))->toBe([1, 2]);
});

it('unwrap() returns a non-collection value unchanged', function () {
    expect(Collection::unwrap([1, 2]))->toBe([1, 2]);
});

it('sliding() yields a window over consecutive items', function () {
    $collection = new Collection([1, 2, 3, 4]);

    expect($collection->sliding(2)->toArray())->toBe([[1, 2], [2, 3], [3, 4]]);
});

it('sliding() honors a custom window size', function () {
    $collection = new Collection([1, 2, 3, 4]);

    expect($collection->sliding(3)->toArray())->toBe([[1, 2, 3], [2, 3, 4]]);
});

it('sliding() honors a custom step', function () {
    $collection = new Collection([1, 2, 3, 4]);

    expect($collection->sliding(2, 2)->toArray())->toBe([[1, 2], [3, 4]]);
});

it('crossJoin() produces the cartesian product', function () {
    $collection = new Collection([1, 2]);

    expect($collection->crossJoin(['a', 'b'])->toArray())
        ->toBe([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']]);
});

it('crossJoin() accepts another collection', function () {
    $collection = new Collection([1, 2]);

    expect($collection->crossJoin(new Collection(['a']))->toArray())
        ->toBe([[1, 'a'], [2, 'a']]);
});

it('mode() returns the most frequent value', function () {
    $collection = new Collection([1, 1, 2, 3]);

    expect($collection->mode())->toBe([1]);
});

it('mode() returns every value tied for most frequent', function () {
    $collection = new Collection([1, 1, 2, 2, 3]);

    expect($collection->mode())->toBe([1, 2]);
});

it('mode() returns null for an empty collection', function () {
    $collection = new Collection([]);

    expect($collection->mode())->toBeNull();
});

it('mode() can read an attribute from item arrays', function () {
    $collection = new Collection([
        ['v' => 5],
        ['v' => 5],
        ['v' => 9],
    ]);

    expect($collection->mode('v'))->toBe([5]);
});

it('sole() returns the only item in a single-item collection', function () {
    $collection = new Collection([42]);

    expect($collection->sole())->toBe(42);
});

it('sole() returns the only item matching a predicate', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->sole(fn ($value) => $value === 2))->toBe(2);
});

it('sole() returns the only item matching a key/value pair', function () {
    $collection = new Collection([['id' => 1], ['id' => 2], ['id' => 3]]);

    expect($collection->sole('id', 2))->toBe(['id' => 2]);
});

it('sole() throws ItemNotFoundException when nothing matches', function () {
    $collection = new Collection([1, 2, 3]);

    $collection->sole(fn ($value) => $value === 99);
})->throws(ItemNotFoundException::class);

it('sole() throws MultipleItemsFoundException when several match', function () {
    $collection = new Collection([1, 2, 2, 3]);

    $collection->sole(fn ($value) => $value === 2);
})->throws(MultipleItemsFoundException::class);

it('takeUntil() takes items until the callback returns true', function () {
    $collection = new Collection([1, 2, 3, 4, 1]);

    expect($collection->takeUntil(fn ($v) => $v >= 3)->values()->toArray())->toBe([1, 2]);
});

it('takeUntil() takes items until a literal value is reached', function () {
    $collection = new Collection([1, 2, 3, 4]);

    expect($collection->takeUntil(3)->values()->toArray())->toBe([1, 2]);
});

it('skipUntil() skips items until the callback returns true', function () {
    $collection = new Collection([1, 2, 3, 4, 1]);

    expect($collection->skipUntil(fn ($v) => $v >= 3)->values()->toArray())->toBe([3, 4, 1]);
});

it('skipUntil() skips items until a literal value is reached', function () {
    $collection = new Collection([1, 2, 3, 4]);

    expect($collection->skipUntil(3)->values()->toArray())->toBe([3, 4]);
});

it('chunkWhile() groups consecutive items while the callback holds', function () {
    $collection = new Collection([1, 2, 2, 3, 5, 5, 5]);

    $chunks = $collection->chunkWhile(fn ($value, $key, $chunk) => $value === $chunk->last())
        ->map(fn ($chunk) => $chunk->values()->toArray())
        ->toArray();

    expect($chunks)->toBe([[1], [2, 2], [3], [5, 5, 5]]);
});

it('chunkWhile() returns an empty collection for empty input', function () {
    $collection = new Collection([]);

    expect($collection->chunkWhile(fn () => true)->toArray())->toBe([]);
});

it('forPage() returns the slice for the given page', function () {
    $collection = new Collection([1, 2, 3, 4, 5, 6, 7]);

    expect($collection->forPage(2, 3)->values()->toArray())->toBe([4, 5, 6]);
});

it('forPage() returns an empty collection past the last page', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->forPage(5, 3)->toArray())->toBe([]);
});

it('whereBetween() keeps items whose attribute is within the range (inclusive)', function () {
    $collection = new Collection([
        ['name' => 'a', 'price' => 10],
        ['name' => 'b', 'price' => 20],
        ['name' => 'c', 'price' => 30],
    ]);

    expect($collection->whereBetween('price', [15, 30])->values()->toArray())
        ->toBe([['name' => 'b', 'price' => 20], ['name' => 'c', 'price' => 30]]);
});

it('whereNotBetween() removes items whose attribute is within the range', function () {
    $collection = new Collection([
        ['name' => 'a', 'price' => 10],
        ['name' => 'b', 'price' => 20],
        ['name' => 'c', 'price' => 30],
    ]);

    expect($collection->whereNotBetween('price', [15, 30])->values()->toArray())
        ->toBe([['name' => 'a', 'price' => 10]]);
});

it('whereInstanceOf() keeps only items of the given type', function () {
    $a = new RuntimeException('x');
    $b = new LogicException('y');
    $collection = new Collection([$a, $b, 'plain']);

    expect($collection->whereInstanceOf(RuntimeException::class)->values()->toArray())->toBe([$a]);
});

it('whereInstanceOf() accepts an array of types', function () {
    $a = new RuntimeException('x');
    $b = new LogicException('y');
    $collection = new Collection([$a, $b, 'plain']);

    expect($collection->whereInstanceOf([RuntimeException::class, LogicException::class])->count())->toBe(2);
});

it('doesntContain() is the inverse of contains()', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->doesntContain(4))->toBeTrue();
    expect($collection->doesntContain(2))->toBeFalse();
});

it('replace() overwrites items by matching key', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

    expect($collection->replace(['b' => 20, 'd' => 40])->toArray())
        ->toBe(['a' => 1, 'b' => 20, 'c' => 3, 'd' => 40]);
});

it('replace() accepts another Collection', function () {
    $collection = new Collection(['a' => 1, 'b' => 2]);

    expect($collection->replace(new Collection(['b' => 9]))->toArray())
        ->toBe(['a' => 1, 'b' => 9]);
});

it('union() keeps existing keys and appends missing ones', function () {
    $collection = new Collection(['a' => 1, 'b' => 2]);

    expect($collection->union(['b' => 20, 'c' => 3])->toArray())
        ->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
});

it('dot() flattens a nested array into dot-notation keys', function () {
    $collection = new Collection(['user' => ['name' => 'Ann', 'roles' => ['admin']]]);

    expect($collection->dot()->toArray())
        ->toBe(['user.name' => 'Ann', 'user.roles.0' => 'admin']);
});

it('undot() expands dot-notation keys back into nested arrays', function () {
    $collection = new Collection(['user.name' => 'Ann', 'user.age' => 30]);

    expect($collection->undot()->toArray())
        ->toBe(['user' => ['name' => 'Ann', 'age' => 30]]);
});

class MapIntoFixture
{
    public function __construct(public mixed $value, public mixed $key = null) {}
}

it('mapInto() instantiates the given class for each item with value and key', function () {
    $collection = new Collection(['x', 'y']);
    $result = $collection->mapInto(MapIntoFixture::class)->toArray();

    expect($result)->each->toBeInstanceOf(MapIntoFixture::class);
    expect($result[0]->value)->toBe('x');
    expect($result[1]->key)->toBe(1);
});

it('mapToGroups() groups items by the key returned from the callback', function () {
    $collection = new Collection([
        ['team' => 'red', 'name' => 'Ann'],
        ['team' => 'blue', 'name' => 'Bob'],
        ['team' => 'red', 'name' => 'Cy'],
    ]);

    $grouped = $collection->mapToGroups(fn ($item) => [$item['team'] => $item['name']])
        ->map(fn ($group) => $group->toArray())
        ->toArray();

    expect($grouped)->toBe(['red' => ['Ann', 'Cy'], 'blue' => ['Bob']]);
});

it('split() divides the collection into the given number of groups', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $groups = $collection->split(3)->map(fn ($g) => $g->values()->toArray())->toArray();

    expect($groups)->toBe([[1, 2], [3, 4], [5]]);
});

it('split() yields fewer groups than requested when items run out', function () {
    $collection = new Collection([1, 2]);

    expect($collection->split(4)->count())->toBe(2);
});

it('splitIn() divides the collection into groups of equal ceil size', function () {
    $collection = new Collection([1, 2, 3, 4, 5]);

    $groups = $collection->splitIn(2)->map(fn ($g) => $g->values()->toArray())->toArray();

    expect($groups)->toBe([[1, 2, 3], [4, 5]]);
});

it('combine() uses the collection values as keys for the given values', function () {
    $collection = new Collection(['name', 'age']);

    expect($collection->combine(['Ann', 30])->toArray())->toBe(['name' => 'Ann', 'age' => 30]);
});

it('combine() accepts another Collection as the values', function () {
    $collection = new Collection(['a', 'b']);

    expect($collection->combine(new Collection([1, 2]))->toArray())->toBe(['a' => 1, 'b' => 2]);
});

it('mergeRecursive() merges nested arrays recursively', function () {
    $collection = new Collection(['user' => ['name' => 'Ann'], 'tags' => ['x']]);

    expect($collection->mergeRecursive(['user' => ['age' => 30], 'tags' => ['y']])->toArray())
        ->toBe(['user' => ['name' => 'Ann', 'age' => 30], 'tags' => ['x', 'y']]);
});

it('replaceRecursive() replaces nested values by key', function () {
    $collection = new Collection(['user' => ['name' => 'Ann', 'age' => 30]]);

    expect($collection->replaceRecursive(['user' => ['age' => 31]])->toArray())
        ->toBe(['user' => ['name' => 'Ann', 'age' => 31]]);
});

it('collapseWithKeys() collapses nested collections preserving keys', function () {
    $collection = new Collection([
        ['a' => 1, 'b' => 2],
        new Collection(['b' => 20, 'c' => 3]),
    ]);

    expect($collection->collapseWithKeys()->toArray())->toBe(['a' => 1, 'b' => 20, 'c' => 3]);
});

it('sortKeys() sorts the collection ascending by key', function () {
    $collection = new Collection(['c' => 3, 'a' => 1, 'b' => 2]);

    expect($collection->sortKeys()->toArray())->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
});

it('sortKeysDesc() sorts the collection descending by key', function () {
    $collection = new Collection(['a' => 1, 'c' => 3, 'b' => 2]);

    expect($collection->sortKeysDesc()->toArray())->toBe(['c' => 3, 'b' => 2, 'a' => 1]);
});

it('sortDesc() sorts the values descending', function () {
    $collection = new Collection([2, 5, 1, 4]);

    expect($collection->sortDesc()->toArray())->toBe([5, 4, 2, 1]);
});

it('sortByDesc() sorts items descending by the callback value', function () {
    $collection = new Collection([
        ['name' => 'a', 'score' => 10],
        ['name' => 'b', 'score' => 30],
        ['name' => 'c', 'score' => 20],
    ]);

    expect($collection->sortByDesc(fn ($item) => $item['score'])->values()->toArray())
        ->toBe([
            ['name' => 'b', 'score' => 30],
            ['name' => 'c', 'score' => 20],
            ['name' => 'a', 'score' => 10],
        ]);
});

it('mapSpread() spreads each nested item as callback arguments', function () {
    $collection = new Collection([[1, 2], [3, 4]]);

    expect($collection->mapSpread(fn ($a, $b) => $a + $b)->toArray())->toBe([3, 7]);
});

it('mapSpread() passes the item key as the final argument', function () {
    $collection = new Collection([[10], [20]]);

    expect($collection->mapSpread(fn ($value, $key) => "$key:$value")->toArray())
        ->toBe(['0:10', '1:20']);
});

it('eachSpread() spreads each nested item and returns the collection', function () {
    $collection = new Collection([[1, 2], [3, 4]]);
    $sums = [];

    $result = $collection->eachSpread(function ($a, $b) use (&$sums) {
        $sums[] = $a + $b;
    });

    expect($sums)->toBe([3, 7])->and($result)->toBe($collection);
});

it('eachSpread() stops when the callback returns false', function () {
    $collection = new Collection([[1], [2], [3]]);
    $seen = [];

    $collection->eachSpread(function ($value) use (&$seen) {
        $seen[] = $value;

        return $value < 2;
    });

    expect($seen)->toBe([1, 2]);
});

it('firstOrFail() returns the first item matching a predicate', function () {
    $collection = new Collection([1, 2, 3]);

    expect($collection->firstOrFail(fn ($value) => $value > 1))->toBe(2);
});

it('firstOrFail() returns the first item matching a key/value pair', function () {
    $collection = new Collection([['id' => 1], ['id' => 2]]);

    expect($collection->firstOrFail('id', 2))->toBe(['id' => 2]);
});

it('firstOrFail() throws ItemNotFoundException when nothing matches', function () {
    $collection = new Collection([1, 2, 3]);

    $collection->firstOrFail(fn ($value) => $value === 99);
})->throws(ItemNotFoundException::class);

it('percentage() returns the share of items matching the predicate', function () {
    $collection = new Collection([1, 2, 3, 4]);

    expect($collection->percentage(fn ($value) => $value % 2 === 0))->toBe(50.0);
});

it('percentage() returns null for an empty collection', function () {
    $collection = new Collection([]);

    expect($collection->percentage(fn () => true))->toBeNull();
});
