<?php

declare(strict_types=1);

namespace Phare\Collections;

use Closure;
use Phare\Collections\Exceptions\ItemNotFoundException;
use Phare\Collections\Exceptions\MultipleItemsFoundException;

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

    /**
     * Map over the items, spreading each item's elements as callback
     * arguments; the item key is appended as the final argument
     * (Laravel parity).
     */
    public function mapSpread(callable $callback): static
    {
        $data = [];
        foreach ($this->data as $key => $chunk) {
            $arguments = $chunk instanceof self ? $chunk->toArray() : (array)$chunk;
            $arguments[] = $key;
            $data[$key] = $callback(...$arguments);
        }

        return new static($data);
    }

    /**
     * Run a callback over each item, spreading the item's elements as
     * callback arguments. Returning false stops iteration (Laravel parity).
     */
    public function eachSpread(callable $callback): static
    {
        foreach ($this->data as $key => $chunk) {
            $arguments = $chunk instanceof self ? $chunk->toArray() : (array)$chunk;
            $arguments[] = $key;
            if ($callback(...$arguments) === false) {
                break;
            }
        }

        return $this;
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
     * Collapse a collection of arrays/collections into a single collection,
     * preserving keys — later keys overwrite earlier ones (Laravel parity).
     */
    public function collapseWithKeys(): static
    {
        $results = [];

        foreach ($this->data as $values) {
            if ($values instanceof self) {
                $values = $values->toArray();
            }

            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $results[$key] = $value;
                }
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
     * Thread the collection through each callable pipe in turn, passing each
     * pipe's result to the next (Laravel parity).
     *
     * @param iterable<callable> $pipes
     * @return mixed
     */
    public function pipeThrough($pipes)
    {
        $carry = $this;
        foreach ($pipes as $pipe) {
            $carry = $pipe($carry);
        }

        return $carry;
    }

    /**
     * Pass the collection into a new instance of the given class
     * (Laravel parity).
     *
     * @param class-string $class
     * @return mixed
     */
    public function pipeInto(string $class)
    {
        return new $class($this);
    }

    /**
     * Reduce the collection into multiple accumulators. The callback receives
     * the accumulators spread as arguments followed by the value and key, and
     * must return an array of the next accumulators (Laravel parity).
     *
     * @param mixed ...$initial
     * @return array<mixed>
     *
     * @throws \UnexpectedValueException when the callback returns a non-array
     */
    public function reduceSpread(callable $callback, ...$initial): array
    {
        $result = $initial;
        foreach ($this->data as $key => $value) {
            $result = $callback(...array_merge($result, [$value, $key]));

            if (!is_array($result)) {
                throw new \UnexpectedValueException(
                    'reduceSpread() expects the reducer to return an array, got ' . get_debug_type($result) . '.'
                );
            }
        }

        return $result;
    }

    /**
     * Verify that every item matches one of the given types (a built-in type
     * name, class name, or interface), returning the collection unchanged
     * (Laravel parity).
     *
     * @param string|array<string> $type
     *
     * @throws \UnexpectedValueException when an item does not match
     */
    public function ensure($type): static
    {
        $allowed = is_array($type) ? $type : [$type];

        foreach ($this->data as $item) {
            $itemType = get_debug_type($item);
            $matched = false;
            foreach ($allowed as $allowedType) {
                if ($itemType === $allowedType || $item instanceof $allowedType) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                throw new \UnexpectedValueException(
                    sprintf('Collection should only include [%s] items, but \'%s\' found.', implode(', ', $allowed), $itemType)
                );
            }
        }

        return $this;
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
     * Return the first item matching a predicate or an attribute comparison,
     * throwing when nothing matches (Laravel parity).
     *
     * @param callable|string|null $key
     * @param mixed $operator
     * @param mixed $value
     * @return mixed
     *
     * @throws ItemNotFoundException when no item matches
     */
    public function firstOrFail($key = null, $operator = null, $value = null)
    {
        $argCount = func_num_args();

        if ($argCount === 0) {
            $items = $this;
        } elseif ($argCount === 1 && !is_string($key) && is_callable($key)) {
            $items = $this->filter($key);
        } else {
            $items = $this->filter($this->wherePredicate((string)$key, $operator, $value, $argCount));
        }

        if ($items->count() === 0) {
            throw new ItemNotFoundException();
        }

        return $items->first();
    }

    /**
     * Return the percentage of items that satisfy the predicate, or null for
     * an empty collection (Laravel parity).
     */
    public function percentage(callable $callback, int $precision = 2): ?float
    {
        $total = $this->count();

        if ($total === 0) {
            return null;
        }

        return round($this->filter($callback)->count() / $total * 100, $precision);
    }

    /**
     * Return the sole item, optionally narrowed by a predicate or an
     * attribute comparison (Laravel parity).
     *
     * @param callable|string|null $key
     * @param mixed $operator
     * @param mixed $value
     * @return mixed
     *
     * @throws ItemNotFoundException when no item matches
     * @throws MultipleItemsFoundException when more than one item matches
     */
    public function sole($key = null, $operator = null, $value = null)
    {
        $argCount = func_num_args();

        if ($argCount === 0) {
            $items = $this;
        } elseif ($argCount === 1 && !is_string($key) && is_callable($key)) {
            $items = $this->filter($key);
        } else {
            $items = $this->filter($this->wherePredicate((string)$key, $operator, $value, $argCount));
        }

        $count = $items->count();

        if ($count === 0) {
            throw new ItemNotFoundException();
        }

        if ($count > 1) {
            throw new MultipleItemsFoundException($count);
        }

        return $items->first();
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
     * Keep items whose attribute value falls within the inclusive range
     * `[$values[0], $values[1]]` (Laravel parity).
     *
     * @param iterable<mixed>|self $values
     */
    public function whereBetween(string $key, $values): static
    {
        [$from, $to] = array_values($this->extractItems($values));

        return $this->filter(function ($item) use ($key, $from, $to) {
            $value = $this->itemValue($item, $key);

            return $value >= $from && $value <= $to;
        });
    }

    /**
     * Remove items whose attribute value falls within the inclusive range
     * `[$values[0], $values[1]]` (Laravel parity).
     *
     * @param iterable<mixed>|self $values
     */
    public function whereNotBetween(string $key, $values): static
    {
        [$from, $to] = array_values($this->extractItems($values));

        return $this->filter(function ($item) use ($key, $from, $to) {
            $value = $this->itemValue($item, $key);

            return $value < $from || $value > $to;
        });
    }

    /**
     * Keep only items that are instances of the given class (or one of the
     * given classes, Laravel parity).
     *
     * @param class-string|array<class-string> $type
     */
    public function whereInstanceOf($type): static
    {
        $types = is_array($type) ? $type : [$type];

        return $this->filter(function ($item) use ($types) {
            foreach ($types as $class) {
                if ($item instanceof $class) {
                    return true;
                }
            }

            return false;
        });
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

    /**
     * Normalize an items argument to an array while preserving its keys.
     */
    private function keyedItems($values): array
    {
        if ($values instanceof self) {
            return $values->toArray();
        }

        if (is_array($values)) {
            return $values;
        }

        return iterator_to_array($values, true);
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

    /**
     * Replace items by key, overwriting existing keys and appending new ones
     * (Laravel parity).
     *
     * @param iterable<mixed>|self $items
     */
    public function replace($items): static
    {
        return new static(array_replace($this->data, $this->keyedItems($items)));
    }

    /**
     * Union the collection with the given items, keeping the collection's
     * own value when a key collides (Laravel parity).
     *
     * @param iterable<mixed>|self $items
     */
    public function union($items): static
    {
        return new static($this->data + $this->keyedItems($items));
    }

    /**
     * Flatten a nested collection into a single level with dot-notation keys
     * (Laravel parity).
     */
    public function dot(): static
    {
        return new static(Arr::dot($this->data));
    }

    /**
     * Expand dot-notation keys back into a nested array — the inverse of
     * dot() (Laravel parity).
     */
    public function undot(): static
    {
        return new static(Arr::undot($this->data));
    }

    /**
     * Combine the collection's values as keys with the given values
     * (Laravel parity).
     *
     * @param iterable<mixed>|self $values
     */
    public function combine($values): static
    {
        return new static(array_combine(
            array_values($this->data),
            array_values($this->keyedItems($values))
        ));
    }

    /**
     * Recursively merge the given items into the collection (Laravel parity).
     *
     * @param iterable<mixed>|self $items
     */
    public function mergeRecursive($items): static
    {
        return new static(array_merge_recursive($this->data, $this->keyedItems($items)));
    }

    /**
     * Recursively replace items by key from the given items (Laravel parity).
     *
     * @param iterable<mixed>|self $items
     */
    public function replaceRecursive($items): static
    {
        return new static(array_replace_recursive($this->data, $this->keyedItems($items)));
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

    /**
     * Extract one attribute from every item, optionally keyed by another.
     *
     * Arrays keep the long-standing behaviour: a null value is kept, an item
     * missing the key is skipped. Objects are read through their properties,
     * ArrayAccess, or __get, and always yield an entry — dropping one would
     * misalign the result with the source collection. Reading through __get is
     * what makes this work for models, which hold their data in $attributes.
     */
    public function pluck($attribute, $key = null): static
    {
        $values = [];
        $keys = [];

        foreach ($this->data as $item) {
            if (!self::pluckHas($item, (string)$attribute)) {
                continue;
            }

            $values[] = self::pluckValue($item, (string)$attribute);

            if ($key !== null) {
                $keys[] = self::pluckValue($item, (string)$key);
            }
        }

        if ($key === null) {
            return new static($values);
        }

        return new static(array_combine($keys, $values));
    }

    protected static function pluckHas(mixed $item, string $key): bool
    {
        if (is_array($item)) {
            return array_key_exists($key, $item);
        }

        return is_object($item);
    }

    protected static function pluckValue(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        if (!is_object($item)) {
            return null;
        }

        if (property_exists($item, $key)) {
            return $item->{$key};
        }

        if ($item instanceof \ArrayAccess && $item->offsetExists($key)) {
            return $item[$key];
        }

        if (method_exists($item, '__get')) {
            return $item->{$key};
        }

        return null;
    }

    public function chunk(int $size, $preserveKeys = true): static
    {
        return new static(Arr::chunk($this->data, $size, $preserveKeys));
    }

    /**
     * Take items until the given callback returns true, or a literal value
     * is reached (Laravel parity).
     */
    public function takeUntil($value): static
    {
        $callback = $this->valueAsCallable($value);
        $data = [];
        foreach ($this->data as $k => $v) {
            if ($callback($v, $k)) {
                break;
            }
            $data[$k] = $v;
        }

        return new static($data);
    }

    /**
     * Skip items until the given callback returns true, or a literal value
     * is reached (Laravel parity).
     */
    public function skipUntil($value): static
    {
        $callback = $this->valueAsCallable($value);
        $data = [];
        $skipping = true;
        foreach ($this->data as $k => $v) {
            if ($skipping && $callback($v, $k)) {
                $skipping = false;
            }
            if (!$skipping) {
                $data[$k] = $v;
            }
        }

        return new static($data);
    }

    /**
     * Chunk consecutive items together while the callback returns true.
     * The callback receives the value, key, and current chunk (Laravel parity).
     */
    public function chunkWhile(callable $callable): static
    {
        $chunks = [];
        $chunk = [];
        foreach ($this->data as $k => $v) {
            if ($chunk === []) {
                $chunk = [$k => $v];

                continue;
            }
            if ($callable($v, $k, new static($chunk))) {
                $chunk[$k] = $v;
            } else {
                $chunks[] = new static($chunk);
                $chunk = [$k => $v];
            }
        }
        if ($chunk !== []) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Slice the collection for the given page (1-indexed, Laravel parity).
     */
    public function forPage(int $page, int $perPage): static
    {
        return $this->slice(max(0, $page - 1) * $perPage, $perPage);
    }

    /**
     * Map each item into a new instance of the given class (Laravel parity).
     *
     * @param class-string $class
     */
    public function mapInto(string $class): static
    {
        $data = [];
        foreach ($this->data as $key => $value) {
            $data[$key] = new $class($value, $key);
        }

        return new static($data);
    }

    /**
     * Map items into a dictionary keyed by the single key/value pair the
     * callback returns, collecting values into plain arrays (Laravel parity).
     */
    public function mapToDictionary(callable $callback): static
    {
        $dictionary = [];
        foreach ($this->data as $key => $value) {
            foreach ($callback($value, $key) as $groupKey => $groupValue) {
                $dictionary[$groupKey][] = $groupValue;
            }
        }

        return new static($dictionary);
    }

    /**
     * Map items into groups keyed by the single key/value pair the callback
     * returns for each item (Laravel parity).
     */
    public function mapToGroups(callable $callback): static
    {
        $groups = [];
        foreach ($this->data as $key => $value) {
            foreach ($callback($value, $key) as $groupKey => $groupValue) {
                $groups[$groupKey][] = $groupValue;
            }
        }

        return new static(array_map(fn ($group) => new static($group), $groups));
    }

    /**
     * Split the collection into the given number of groups, distributing any
     * remainder across the leading groups (Laravel parity).
     */
    public function split(int $numberOfGroups): static
    {
        if ($numberOfGroups < 1) {
            return new static([]);
        }

        $values = array_values($this->data);
        $count = count($values);
        $groupSize = intdiv($count, $numberOfGroups);
        $remainder = $count % $numberOfGroups;

        $groups = [];
        $offset = 0;
        for ($i = 0; $i < $numberOfGroups; $i++) {
            $size = $groupSize + ($i < $remainder ? 1 : 0);
            if ($size === 0) {
                continue;
            }
            $groups[] = new static(array_slice($values, $offset, $size));
            $offset += $size;
        }

        return new static($groups);
    }

    /**
     * Split the collection into the given number of groups, filling each
     * group to its ceiling size before starting the next (Laravel parity).
     */
    public function splitIn(int $numberOfGroups): static
    {
        if ($numberOfGroups < 1 || $this->count() === 0) {
            return new static([]);
        }

        $size = (int)ceil($this->count() / $numberOfGroups);

        return $this->chunk($size, false)->map(fn ($chunk) => new static($chunk));
    }

    /**
     * Wrap a value in a callback; non-string callables pass through,
     * everything else becomes a loose-equality match (Laravel parity).
     */
    protected function valueAsCallable($value): callable
    {
        if (!is_string($value) && is_callable($value)) {
            return $value;
        }

        return fn ($item) => $item == $value;
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

    /**
     * Determine whether the given value is absent from the collection
     * (inverse of contains(), Laravel parity).
     *
     * @param mixed $attribute
     */
    public function doesntContain($attribute): bool
    {
        return !$this->contains($attribute);
    }

    /**
     * Determine whether the collection holds exactly one item (Laravel parity).
     */
    public function containsOneItem(): bool
    {
        return $this->count() === 1;
    }

    /**
     * Determine whether the collection holds more than one item (Laravel parity).
     */
    public function containsManyItems(): bool
    {
        return $this->count() > 1;
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

    /**
     * Sort the collection ascending by key — Laravel-named alias of
     * {@see sortKey()}.
     */
    public function sortKeys(): static
    {
        return $this->sortKey();
    }

    /**
     * Sort the collection descending by key — Laravel-named alias of
     * {@see rsortKey()}.
     */
    public function sortKeysDesc(): static
    {
        return $this->rsortKey();
    }

    /**
     * Sort the values descending — Laravel-named alias of {@see rsort()}.
     *
     * @param mixed $attribute
     */
    public function sortDesc($attribute = null): static
    {
        return $this->rsort($attribute);
    }

    /**
     * Sort items descending by the callback value — Laravel-named alias of
     * {@see rsortBy()}.
     */
    public function sortByDesc(?callable $attribute = null): static
    {
        return $this->rsortBy($attribute);
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

    /**
     * Set the given key on the collection, overwriting any existing value,
     * and return the collection (Laravel parity).
     *
     * @param array-key $key
     * @param mixed $value
     */
    public function put($key, $value): static
    {
        $data = $this->data;
        $data[$key] = $value;
        $this->data = $data;

        return $this;
    }

    /**
     * Remove the given keys from the collection and return it (Laravel parity).
     *
     * @param array-key ...$keys
     */
    public function forget(...$keys): static
    {
        $data = $this->data;
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        $this->data = $data;

        return $this;
    }

    /**
     * Return the value at the given key, storing and returning the supplied
     * default (resolved if callable) when the key is absent (Laravel parity).
     *
     * @param array-key $key
     * @param mixed $value
     * @return mixed
     */
    public function getOrPut($key, $value)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $resolved = $value instanceof Closure ? $value() : $value;
        $this->put($key, $resolved);

        return $resolved;
    }

    /**
     * Map the items in place via the callback and return the collection
     * (Laravel parity — the mutating counterpart of map()).
     */
    public function transform(callable $callback): static
    {
        $data = [];
        foreach ($this->data as $key => $value) {
            $data[$key] = $callback($value, $key);
        }
        $this->data = $data;

        return $this;
    }

    /**
     * Remove and return a slice of the collection, optionally replacing it
     * with the given items. Mutates the collection (Laravel parity).
     *
     * @param array<mixed> $replacement
     */
    public function splice(int $offset, ?int $length = null, array $replacement = []): static
    {
        $data = array_values($this->data);
        $removed = $length === null
            ? array_splice($data, $offset)
            : array_splice($data, $offset, $length, $replacement);
        $this->data = $data;

        return new static($removed);
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
