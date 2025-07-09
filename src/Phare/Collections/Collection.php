<?php

declare(strict_types=1);

namespace Phare\Collections;

use Closure;

class Collection extends \Phalcon\Support\Collection
{
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
