<?php

declare(strict_types=1);

namespace Phare\Collections;

use Closure;

class Collection extends \Phalcon\Support\Collection
{
    /**
     * Create a new collection (Laravel parity).
     *
     * @param iterable<mixed>|self $items
     */
    public static function make($items = []): static
    {
        $items = $items instanceof self ? $items->toArray() : (array)$items;

        return new static($items);
    }

    /**
     * Create a collection by invoking the callback a given number of times.
     * Without a callback, yields the integers 1..$number (Laravel parity).
     */
    public static function times(int $number, ?callable $callback = null): static
    {
        if ($number < 1) {
            return new static([]);
        }

        $items = range(1, $number);

        if ($callback !== null) {
            $items = array_map($callback, $items);
        }

        return new static($items);
    }

    /**
     * Create a collection over a numeric range (Laravel parity).
     *
     * @param int|float|string $from
     * @param int|float|string $to
     */
    public static function range($from, $to, int|float $step = 1): static
    {
        return new static(range($from, $to, $step));
    }

    /**
     * Wrap the given value in a collection if it is not one already
     * (Laravel parity).
     *
     * @param mixed $value
     */
    public static function wrap($value): static
    {
        if ($value instanceof self) {
            return new static($value->toArray());
        }

        return new static(is_array($value) ? $value : [$value]);
    }

    /**
     * Get the underlying items of a collection, or return the value as-is
     * when it is not a collection (Laravel parity).
     *
     * @param mixed $value
     * @return mixed
     */
    public static function unwrap($value)
    {
        return $value instanceof self ? $value->toArray() : $value;
    }

    public function first(?callable $callable = null, $default = null)
    {
        return Arr::first($this->data, $callable) ?: $default;
    }

    public function last(?callable $callable = null, $default = null)
    {
        return Arr::last($this->data, $callable) ?: $default;
    }

    public function group(string|callable $key): static
    {
        return new static(Arr::group($this->data, $key));
    }

    public function values(): static
    {
        return new static($this->getValues());
    }

    public function keys($search_value = null): static
    {
        if ($search_value !== null) {
            return new static(Arr::keys($this->data, $search_value));
        }

        return new static($this->getKeys(true));
    }

    public function except(...$keys): static
    {
        return new static(Arr::blacklist($this->data, $keys));
    }

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->data));
    }

    public function mapWithKey(callable $callback): static
    {
        $data = [];
        foreach ($this->data as $k => $v) {
            $data[$k] = $callback($v, $k);
        }

        return new static($data);
    }

    public function mapWithKeys(callable $callback): static
    {
        $result = [];

        foreach ($this->data as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    public function keyBy(callable $keyBy): static
    {
        $results = [];

        foreach ($this->data as $key => $item) {
            $resolvedKey = $keyBy($item, $key);

            if (is_object($resolvedKey)) {
                $resolvedKey = (string)$resolvedKey;
            }

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    public function innerJoin(
        array $inner,
        ?callable $outerKeySelector = null,
        ?callable $innerKeySelector = null,
        ?callable $resultSelectorValue = null,
        ?callable $resultSelectorKey = null
    ): static {
        $collectedInner = new static($inner);

        if ($outerKeySelector === null) {
            $outerKeySelector = static function ($v, $k) {
                return $k;
            };
        }
        if ($innerKeySelector === null) {
            $innerKeySelector = static function ($v, $k) {
                return $k;
            };
        }
        if ($resultSelectorValue === null) {
            $resultSelectorValue = static function ($v1, $v2, $k) {
                return [$v1, $v2];
            };
        }
        if ($resultSelectorKey === null) {
            $resultSelectorKey = static function ($v1, $v2, $k) {
                return $k;
            };
        }

        $result = [];
        $lookup = $collectedInner->group($innerKeySelector);
        foreach ($this as $ok => $ov) {
            $key = $outerKeySelector($ov, $ok);
            if (!isset($lookup[$key])) {
                continue;
            }
            foreach ($lookup[$key] as $iv) {
                $result[$resultSelectorKey($ov, $iv, $key)] =
                    $resultSelectorValue($ov, $iv, $key);
            }
        }

        return new static($result);
    }

    public function flatMap($callable): static
    {
        $flattened = [];
        array_walk_recursive(
            $this->data,
            static function ($v, $k) use (&$flattened, $callable) {
                $flattened[$k] = $callable($v);
            }
        );

        return new static($flattened);
    }

    public function filter(?callable $callback = null): static
    {
        return new static(
            $callback === null ?
                array_filter($this->data) :
                array_filter($this->data, $callback)
        );
    }

    public function filterWithKeys(callable $callback): static
    {
        return new static(array_filter($this->data, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Inverse of {@see filter()} — keep only items the callback rejects.
     */
    public function reject(callable $callback): static
    {
        return new static(array_filter($this->data, static fn ($value) => !$callback($value)));
    }

    /**
     * Reduce the collection to a single value (Laravel parity).
     *
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->data, $callback, $initial);
    }

    /**
     * Run a callback over every item. Returning false from the callback
     * stops iteration early (Laravel parity).
     */
    public function each(callable $callback): static
    {
        foreach ($this->data as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Determine whether every item satisfies the given predicate.
     */
    public function every(callable $callback): bool
    {
        foreach ($this->data as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reverse the order of the items, preserving keys (Laravel parity).
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->data, true));
    }

    /**
     * Append the given items onto the end of the collection (Laravel parity).
     *
     * @param iterable<mixed>|self $source
     */
    public function concat($source): static
    {
        $items = $source instanceof self ? $source->toArray() : (array)$source;
        $result = $this->data;

        foreach ($items as $value) {
            $result[] = $value;
        }

        return new static($result);
    }

    /**
     * Collapse a collection of arrays into a single, flat collection
     * (one level deep — Laravel parity).
     */
    public function collapse(): static
    {
        $results = [];

        foreach ($this->data as $values) {
            if ($values instanceof self) {
                $values = $values->toArray();
            }

            if (is_array($values)) {
                $results = array_merge($results, $values);
            }
        }

        return new static($results);
    }

    /**
     * Create a new collection of every nth element (Laravel parity).
     */
    public function nth(int $step, int $offset = 0): static
    {
        $result = [];
        $position = 0;

        foreach (array_slice(array_values($this->data), $offset) as $item) {
            if ($position % $step === 0) {
                $result[] = $item;
            }

            $position++;
        }

        return new static($result);
    }

    /**
     * Pass the collection to the callback and return the collection
     * (Laravel parity — useful for side effects mid-chain).
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Pass the collection to the callback and return the callback's result.
     *
     * @return mixed
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Split the collection into two — items that pass the predicate and
     * items that fail it (Laravel parity).
     *
     * @return array{0: static, 1: static}
     */
    public function partition(callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($this->data as $key => $value) {
            if ($callback($value, $key)) {
                $passed[$key] = $value;
            } else {
                $failed[$key] = $value;
            }
        }

        return [new static($passed), new static($failed)];
    }

    /**
     * Search the collection for a value (or the first item satisfying a
     * predicate) and return its key, or false when absent (Laravel parity).
     *
     * @param mixed $value
     * @return int|string|false
     */
    public function search($value, bool $strict = false)
    {
        if (!is_string($value) && is_callable($value)) {
            foreach ($this->data as $key => $item) {
                if ($value($item, $key)) {
                    return $key;
                }
            }

            return false;
        }

        return array_search($value, $this->data, $strict);
    }

    /**
     * Keep only the items whose keys are in the given set (Laravel parity).
     * Inverse of {@see except()}.
     *
     * @param int|string|array<int, int|string> ...$keys
     */
    public function only(...$keys): static
    {
        if (count($keys) === 1 && is_array($keys[0])) {
            $keys = $keys[0];
        }

        return new static(array_intersect_key($this->data, array_flip($keys)));
    }

    /**
     * Join the items with a glue string, optionally using a distinct glue
     * before the final item (Laravel parity).
     */
    public function join(string $glue, string $finalGlue = ''): string
    {
        $values = array_values($this->data);

        if ($finalGlue === '') {
            return implode($glue, $values);
        }

        $count = count($values);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return (string)$values[0];
        }

        $finalItem = array_pop($values);

        return implode($glue, $values) . $finalGlue . $finalItem;
    }

    /**
     * Pad the collection to the given size with a value (Laravel parity).
     * A negative size pads to the left.
     *
     * @param mixed $value
     */
    public function pad(int $size, $value): static
    {
        return new static(array_pad($this->data, $size, $value));
    }

    /**
     * Filter items by an attribute comparison (Laravel parity).
     *
     * Two-argument form (`where('age', 30)`) tests equality; the
     * three-argument form takes an explicit operator.
     *
     * @param mixed $operator
     * @param mixed $value
     */
    public function where(string $key, $operator = null, $value = null): static
    {
        return $this->filter($this->wherePredicate($key, $operator, $value, func_num_args()));
    }

    /**
     * Keep items whose attribute value is in the given set (Laravel parity).
     *
     * @param iterable<mixed>|self $values
     */
    public function whereIn(string $key, $values): static
    {
        $values = $this->extractItems($values);

        return $this->filter(fn ($item) => in_array($this->itemValue($item, $key), $values, false));
    }

    /**
     * Remove items whose attribute value is in the given set (Laravel parity).
     *
     * @param iterable<mixed>|self $values
     */
    public function whereNotIn(string $key, $values): static
    {
        $values = $this->extractItems($values);

        return $this->filter(fn ($item) => !in_array($this->itemValue($item, $key), $values, false));
    }

    /**
     * Return the first item matching an attribute comparison (Laravel parity).
     *
     * @param mixed $operator
     * @param mixed $value
     * @return mixed
     */
    public function firstWhere(string $key, $operator = null, $value = null)
    {
        return $this->first($this->wherePredicate($key, $operator, $value, func_num_args()));
    }

    /**
     * Keep items whose attribute (or the item itself when $key is null) is
     * null (Laravel parity).
     */
    public function whereNull(?string $key = null): static
    {
        return $this->filter(
            fn ($item) => ($key === null ? $item : $this->itemValue($item, $key)) === null
        );
    }

    /**
     * Keep items whose attribute (or the item itself when $key is null) is
     * not null (Laravel parity).
     */
    public function whereNotNull(?string $key = null): static
    {
        return $this->filter(
            fn ($item) => ($key === null ? $item : $this->itemValue($item, $key)) !== null
        );
    }

    /**
     * Return the item immediately before the given value (or the first item
     * satisfying a predicate), or null (Laravel parity).
     *
     * @param mixed $value
     * @return mixed
     */
    public function before($value, bool $strict = false)
    {
        return $this->adjacentItem($value, $strict, -1);
    }

    /**
     * Return the item immediately after the given value (or the first item
     * satisfying a predicate), or null (Laravel parity).
     *
     * @param mixed $value
     * @return mixed
     */
    public function after($value, bool $strict = false)
    {
        return $this->adjacentItem($value, $strict, 1);
    }

    /**
     * Resolve the item at a relative offset from the first match of $value.
     *
     * @param mixed $value
     * @return mixed
     */
    private function adjacentItem($value, bool $strict, int $direction)
    {
        $key = $this->search($value, $strict);

        if ($key === false) {
            return;
        }

        $keys = array_keys($this->data);
        $position = array_search($key, $keys, true);

        if ($position === false) {
            return;
        }

        $target = $position + $direction;

        if ($target < 0 || $target > count($keys) - 1) {
            return;
        }

        return $this->data[$keys[$target]];
    }

    /**
     * Build the predicate closure shared by where()/firstWhere().
     */
    private function wherePredicate(string $key, $operator, $value, int $argCount): callable
    {
        if ($argCount === 1) {
            return fn ($item) => (bool)$this->itemValue($item, $key);
        }

        if ($argCount === 2) {
            $value = $operator;
            $operator = '=';
        }

        return fn ($item) => $this->compareValues($this->itemValue($item, $key), (string)$operator, $value);
    }

    /**
     * Read an attribute from an array or object item.
     *
     * @param mixed $item
     * @return mixed
     */
    private function itemValue($item, string $key)
    {
        if (is_array($item) || $item instanceof \ArrayAccess) {
            return $item[$key] ?? null;
        }

        if (is_object($item)) {
            return $item->$key ?? null;
        }
    }

    /**
     * Compare a retrieved value against an expected value with an operator.
     *
     * @param mixed $retrieved
     * @param mixed $value
     */
    private function compareValues($retrieved, string $operator, $value): bool
    {
        return match ($operator) {
            '!=', '<>' => $retrieved != $value,
            '===' => $retrieved === $value,
            '!==' => $retrieved !== $value,
            '<' => $retrieved < $value,
            '>' => $retrieved > $value,
            '<=' => $retrieved <= $value,
            '>=' => $retrieved >= $value,
            default => $retrieved == $value,
        };
    }

    /**
     * Normalize an array/Collection argument into a plain array of values.
     *
     * @param iterable<mixed>|self $values
     * @return array<int, mixed>
     */
    private function extractItems($values): array
    {
        if ($values instanceof self) {
            return array_values($values->toArray());
        }

        return array_values((array)$values);
    }

    public function fill($val): static
    {
        return new static(array_fill_keys(array_keys($this->data), $val));
    }

    public function fillKeys($val): static
    {
        return new static(array_fill_keys($this->data, $val));
    }

    public function flip(): static
    {
        return new static(array_flip($this->data));
    }

    public function exists($value, $strict = true): bool
    {
        return in_array($value, $this->data, $strict);
    }

    public function implode(string $glue)
    {
        return implode($glue, $this->data);
    }

    public function merge($haystack): static
    {
        return new static(
            array_merge(
                $this->data,
                is_array($haystack) ?
                    $haystack : iterator_to_array($haystack)
            )
        );
    }

    public function zip(array ...$supplementary): static
    {
        return new static(array_map(null, $this->data, ...$supplementary));
    }

    public function unique(): static
    {
        return new static(Arr::unique($this->data));
    }

    public function flatten(int $depth = -1): static
    {
        if ($depth === -1) {
            return new static(Arr::flatten($this->data, true));
        }

        if ($depth === 1) {
            return new static(Arr::flatten($this->data));
        }

        return new static(static::flattenLaravel($this->data, $depth));
    }

    public static function flattenLaravel($array, $depth)
    {
        $result = [];

        foreach ($array as $item) {
            $item = $item instanceof Collection ? $item->toArray() : $item;

            if (!is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1
                    ? array_values($item)
                    : static::flattenLaravel($item, $depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    public function take(int $limit): static
    {
        if ($limit < 0) {
            return new static(array_slice($this->data, $limit, null, true));
        }

        return new static(array_slice($this->data, 0, $limit, true));
    }

    public function takeWhile(callable $callable): static
    {
        $data = [];
        foreach ($this->data as $k => $v) {
            $result = $callable($v, $k);
            if (!$result) {
                break;
            }
            $data[$k] = $v;
        }

        return new static($data);
    }

    public function skip(int $count): static
    {
        return new static(array_slice($this->data, $count, null, true));
    }

    public function skipWhile(callable $callable): static
    {
        $data = [];
        $skipping = true;
        foreach ($this->data as $k => $v) {
            if ($skipping) {
                $result = $callable($v, $k);
                if (!$result) {
                    $skipping = false;
                }
            }
            if (!$skipping) {
                $data[$k] = $v;
            }
        }

        return new static($data);
    }

    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->data, $offset, $length, true));
    }

    public function pluck($attribute, $key = null): static
    {
        $values = Arr::pluck($this->data, $attribute);
        if ($key === null) {
            return new static($values);
        }

        return new static(array_combine(Arr::pluck($this->data, $key), $values));
    }

    public function chunk(int $size, $preserveKeys = true): static
    {
        return new static(Arr::chunk($this->data, $size, $preserveKeys));
    }

    /**
     * Create a collection of sliding windows over consecutive items
     * (Laravel parity).
     */
    public function sliding(int $size = 2, int $step = 1): static
    {
        $values = array_values($this->data);
        $count = count($values);
        $windows = [];

        for ($i = 0; $i + $size <= $count; $i += $step) {
            $windows[] = array_slice($values, $i, $size);
        }

        return new static($windows);
    }

    /**
     * Cross-join the collection with the given iterables, producing the
     * cartesian product of all combinations (Laravel parity).
     *
     * @param iterable<mixed>|self ...$arrays
     */
    public function crossJoin(...$arrays): static
    {
        $sources = [array_values($this->data)];

        foreach ($arrays as $array) {
            $sources[] = $array instanceof self
                ? array_values($array->toArray())
                : array_values((array)$array);
        }

        $results = [[]];

        foreach ($sources as $source) {
            $appended = [];

            foreach ($results as $product) {
                foreach ($source as $item) {
                    $appended[] = [...$product, $item];
                }
            }

            $results = $appended;
        }

        return new static($results);
    }

    /**
     * Return the most frequently occurring value(s), sorted ascending, or
     * null when the collection is empty (Laravel parity).
     *
     * @return array<int, mixed>|null
     */
    public function mode(?string $key = null): ?array
    {
        if ($this->isEmpty()) {
            return null;
        }

        $values = $key === null
            ? array_values($this->data)
            : array_map(fn ($item) => $this->itemValue($item, $key), array_values($this->data));

        $counts = [];
        $representatives = [];

        foreach ($values as $value) {
            $hash = is_scalar($value) ? (string)$value : serialize($value);
            $counts[$hash] = ($counts[$hash] ?? 0) + 1;
            $representatives[$hash] = $value;
        }

        $highest = max($counts);
        $modes = [];

        foreach ($counts as $hash => $count) {
            if ($count === $highest) {
                $modes[] = $representatives[$hash];
            }
        }

        sort($modes);

        return $modes;
    }

    public function contains($attribute)
    {
        return in_array($attribute, $this->data, true);
    }

    public function containsKey($attribute)
    {
        return array_key_exists($attribute, $this->data);
    }

    public function sort($attribute = null): static
    {
        if ($attribute === null) {
            $data = $this->data;
            sort($data);

            return new static($data);
        }

        if (is_callable($attribute)) {
            $data = $this->data;
            usort($data, $attribute);

            return new static($data);
        }

        return new static(Arr::order($this->data, $attribute, 'asc'));
    }

    public function sortBy(?callable $attribute = null): static
    {
        $data = $this->data;
        usort($data, static fn ($x, $y) => $attribute($x) <=> $attribute($y));

        return new static($data);
    }

    public function rsort($attribute = null): static
    {
        if ($attribute === null) {
            $data = $this->data;
            rsort($data);

            return new static($data);
        }

        return new static(Arr::order($this->data, $attribute, 'desc'));
    }

    public function rsortBy(?callable $attribute = null): static
    {
        $data = $this->data;
        usort($data, static fn ($x, $y) => ($attribute($x) <=> $attribute($y)) * -1);

        return new static($data);
    }

    public function sortKey(): static
    {
        $data = $this->data;
        ksort($data, SORT_REGULAR);

        return new static($data);
    }

    public function rsortKey(): static
    {
        $data = $this->data;
        krsort($data, SORT_REGULAR);

        return new static($data);
    }

    public function max($attribute = null)
    {
        $max = $this->rsort($attribute)->first();

        return is_array($max) ? $max[$attribute] : $max;
    }

    public function min($attribute = null)
    {
        $min = $this->sort($attribute)->first();

        return is_array($min) ? $min[$attribute] : $min;
    }

    public function sum($attribute = null)
    {
        $data = $this->data;
        if ($attribute !== null) {
            $data = $this->pluck($attribute)->toArray();
        }

        return array_sum($data);
    }

    public function avg($attribute = null)
    {
        if ($this->count() > 0) {
            return $this->sum($attribute) / $this->count();
        }
        // 'nil'
    }

    public function median($attribute = null)
    {
        $values = $attribute === null ?
            $this->sort()->toArray() :
            $this->pluck($attribute)->toArray();

        $c = count($values);
        if ($c % 2 === 0) {
            return ($values[($c / 2) - 1] + $values[($c / 2)]) / 2;
        }

        return $values[floor($c / 2)];
    }

    public function random(int $count = 1)
    {
        if ($count < 1) {
            return;
        }

        if ($count > 1) {
            return array_map(
                function ($i) {
                    return $this->data[$i];
                },
                array_rand($this->data, $count)
            );
        }

        return $this->data[array_rand($this->data)];
    }

    public function shuffle()
    {
        shuffle($this->data);

        return $this;
    }

    public function pop()
    {
        $data = $this->data;
        $v = array_pop($data);
        $this->data = $data;

        return $v;
    }

    public function shift()
    {
        $data = $this->data;
        $v = array_shift($data);
        $this->data = $data;

        return $v;
    }

    public function prepend(...$values)
    {
        $data = $this->data;
        foreach ($values as $v) {
            array_unshift($data, $v);
        }
        $this->data = $data;

        return $this;
    }

    public function push(...$values)
    {
        $data = $this->data;
        foreach ($values as $v) {
            $data[] = $v;
        }
        $this->data = $data;

        return $this;
    }

    public function pull($key)
    {
        if (isset($this->data[$key])) {
            $data = $this->data;
            $v = $this->data[$key];
            unset($data[$key]);
            $this->data = $data;

            return $v;
        }
    }

    public function when($condition, callable $callable)
    {
        if ($condition) {
            $callable($this);
        }

        return $this;
    }

    public function unless($condition, callable $callable)
    {
        return $this->when(!$condition, $callable);
    }

    /**
     * Run the callback when the collection is empty, otherwise run the
     * optional default callback (Laravel parity).
     */
    public function whenEmpty(callable $callback, ?callable $default = null): static
    {
        if ($this->isEmpty()) {
            $callback($this);
        } elseif ($default !== null) {
            $default($this);
        }

        return $this;
    }

    /**
     * Run the callback when the collection is not empty, otherwise run the
     * optional default callback (Laravel parity).
     */
    public function whenNotEmpty(callable $callback, ?callable $default = null): static
    {
        if ($this->isNotEmpty()) {
            $callback($this);
        } elseif ($default !== null) {
            $default($this);
        }

        return $this;
    }

    /**
     * Alias of {@see whenNotEmpty()} (Laravel parity).
     */
    public function unlessEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->whenNotEmpty($callback, $default);
    }

    /**
     * Alias of {@see whenEmpty()} (Laravel parity).
     */
    public function unlessNotEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->whenEmpty($callback, $default);
    }

    public function diff(array $items): static
    {
        return new static(array_diff($this->data, $items));
    }

    public function duplicates($callback = null): static
    {
        if ($callback === null) {
            $callback = $this->identity();
        }

        $items = $this->map($callback);

        $uniqueItems = $items->unique();

        $compare = $this->duplicateComparator(true);

        $duplicates = new static();

        foreach ($items as $key => $value) {
            if ($uniqueItems->isNotEmpty() && $compare($value, $uniqueItems->first())) {
                $uniqueItems->shift();
            } else {
                $duplicates[$key] = $value;
            }
        }

        return $duplicates;
    }

    protected function duplicateComparator(bool $strict)
    {
        if ($strict) {
            return static function ($a, $b) {
                return $a === $b;
            };
        }

        return static function ($a, $b) {
            return $a == $b;
        };
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function countBy($countBy = null): static
    {
        if ($countBy === null) {
            $countBy = $this->identity();
        }

        $counts = [];

        foreach ($this as $key => $value) {
            $group = $countBy($value, $key);

            if (empty($counts[$group])) {
                $counts[$group] = 0;
            }

            $counts[$group]++;
        }

        return new static($counts);
    }

    protected function identity(): Closure
    {
        return static function ($value) {
            return $value;
        };
    }

    public function exceptBy(array $inner, ?callable $outerKeySelector = null, ?callable $innerKeySelector = null): static
    {
        if ($outerKeySelector === null) {
            $outerKeySelector = static function ($v, $k) {
                return $k;
            };
        }
        if ($innerKeySelector === null) {
            $innerKeySelector = static function ($v, $k) {
                return $k;
            };
        }

        $lookup = $this->group($outerKeySelector);
        foreach ($inner as $ik => $iv) {
            $key = $innerKeySelector($iv, $ik);
            if (isset($lookup[$key])) {
                unset($lookup[$key]);
            }
        }

        $result = [];
        foreach ($lookup as $outers) {
            foreach ($outers as $outer) {
                $result[] = $outer;
            }
        }

        return new static($result);
    }

    public function maxBy(callable $callback)
    {
        $maxValue = PHP_INT_MIN;
        $maxValueElement = null;
        foreach ($this as $key => $value) {
            $tmp = $callback($value, $key);
            if ($maxValue < $tmp) {
                $maxValue = $tmp;
                $maxValueElement = $value;
            }
        }

        return $maxValueElement;
    }

    public function minBy(callable $callback)
    {
        $minValue = PHP_INT_MAX;
        $minValueElement = null;
        foreach ($this as $value) {
            $tmp = $callback($value);
            if ($minValue > $tmp) {
                $minValue = $tmp;
                $minValueElement = $value;
            }
        }

        return $minValueElement;
    }

    public function intersect($inner): static
    {
        $collectedInner = new static($inner);

        return new static(array_intersect($this->toArray(), $collectedInner->toArray()));
    }
}
