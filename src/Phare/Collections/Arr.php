<?php

namespace Phare\Collections;

use Phalcon\Support\HelperFactory;

/**
 * ServiceLocator implementation for helpers
 *
 * @method static array blacklist(array $collection, array $blackList)
 * @method static array chunk(array $collection, int $size, bool $preserveKeys = false)
 * @method static mixed first(array $collection, callable $method = null)
 * @method static mixed firstKey(array $collection, callable $method = null)
 * @method static array flatten(array $collection, bool $deep = false)
 * @method static mixed get(array $collection, $index, $defaultValue = null, string $cast = null)
 * @method static array group(array $collection, $method)
 * @method static bool has(array $collection, $index)
 * @method static bool isUnique(array $collection)
 * @method static mixed last(array $collection, callable $method = null)
 * @method static mixed lastKey(array $collection, callable $method = null)
 * @method static array order(array $collection, $attribute, string $order = 'asc')
 * @method static array pluck(array $collection, string $element)
 * @method static array set(array $collection, $value, $index = null)
 * @method static array sliceLeft(array $collection, int $elements = 1)
 * @method static array sliceRight(array $collection, int $elements = 1)
 * @method static array split(array $collection)
 * @method static object toObject(array $collection)
 * @method static bool validateAll(array $collection, callable $method)
 * @method static bool validateAny(array $collection, callable $method)
 * @method static array whitelist(array $collection, array $whiteList)
 */
class Arr
{
    private static self $instance;

    private HelperFactory $helper;

    /**
     * Private constructor to prevent multiple instances.
     */
    private function __construct()
    {
        $this->helper = new HelperFactory();
    }

    /**
     * Call static methods on the singleton instance or forward to the helper.
     */
    public static function __callStatic(string $name, array $arguments)
    {
        self::$instance ??= new self();
        if (method_exists(self::$instance, $name)) {
            return self::$name(...$arguments);
        }

        return self::$instance->helper->$name(...$arguments);
    }

    /**
     * Remove duplicate values from an array.
     */
    public static function unique(array $collection, $sort_flags = SORT_REGULAR): array
    {
        return array_unique($collection, $sort_flags);
    }

    /**
     * Return all the keys of a given search value in an array.
     */
    public static function keys(array $collection, $search_value): array
    {
        return array_keys($collection, $search_value, true);
    }

    /**
     * Exclude zero values from an array.
     */
    public static function excludeZero(array $values): array
    {
        return array_values(array_filter($values, fn ($x) => $x !== 0));
    }

    /**
     * Determine if an array contains any non-zero duplicate values.
     */
    public static function containsDuplicateValue(array $values): bool
    {
        return array_sum($values) !== array_sum(array_unique($values));
    }

    /**
     * Calculate the depth of a nested array.
     */
    public static function depth($arr, $c = 0): int
    {
        if (is_array($arr) && count($arr)) {
            $c++;
            $_c = [$c];
            foreach ($arr as $v) {
                if (is_array($v) && count($v)) {
                    $_c[] = self::depth($v, $c);
                }
            }

            return max($_c);
        }

        return $c;
    }

    /**
     * Fetch a value from an array by key or return null if not found.
     */
    public static function fetch(array $needle, $key)
    {
        return $needle[$key] ?? null;
    }

    /**
     * Determine whether the given value is array accessible.
     */
    public static function accessible(mixed $value): bool
    {
        return is_array($value) || $value instanceof \ArrayAccess;
    }

    /**
     * Determine if the given key exists in the provided array.
     */
    public static function exists(array|\ArrayAccess $array, string|int $key): bool
    {
        if ($array instanceof \ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }

    /**
     * Get an item from an array using "dot" notation.
     */
    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && $value !== []) {
                $results = array_merge($results, self::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Convert a flattened "dot" notation array into an expanded array.
     */
    public static function undot(array $array): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $keys = explode('.', (string)$key);
            $current = &$results;

            foreach ($keys as $segment) {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }

            $current = $value;
            unset($current);
        }

        return $results;
    }

    /**
     * Get all of the given array except for a specified array of keys.
     */
    public static function except(array $array, array|string $keys): array
    {
        $keys = (array)$keys;

        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Get a subset of the items from the given array.
     */
    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array)$keys));
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     */
    public static function forget(array &$array, array|string $keys): void
    {
        $keys = (array)$keys;

        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                unset($array[$key]);

                continue;
            }

            $parts = explode('.', $key);
            $current = &$array;

            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    continue 2;
                }
                $current = &$current[$part];
            }

            unset($current[array_shift($parts)]);
        }
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     */
    public static function hasDot(array $array, string|array $keys): bool
    {
        $keys = (array)$keys;

        foreach ($keys as $key) {
            $subArray = $array;

            if (array_key_exists($key, $array)) {
                continue;
            }

            foreach (explode('.', $key) as $segment) {
                if (!is_array($subArray) || !array_key_exists($segment, $subArray)) {
                    return false;
                }
                $subArray = $subArray[$segment];
            }
        }

        return true;
    }

    /**
     * Get a value from the array using "dot" notation.
     */
    public static function getDot(array $array, ?string $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     */
    public static function setDot(array &$array, ?string $key, mixed $value): array
    {
        if ($key === null) {
            return $array = $value;
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Determine if an array is associative.
     */
    public static function isAssoc(array $array): bool
    {
        return !array_is_list($array);
    }

    /**
     * Determine if an array is a list (sequential integer keys from 0).
     */
    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }

    /**
     * Join all items using a string. The final items can use a separate glue string.
     */
    public static function join(array $array, string $glue, string $finalGlue = ''): string
    {
        if ($finalGlue === '') {
            return implode($glue, $array);
        }

        if (count($array) === 0) {
            return '';
        }

        if (count($array) === 1) {
            return end($array);
        }

        $finalItem = array_pop($array);

        return implode($glue, $array) . $finalGlue . $finalItem;
    }

    /**
     * Push an item onto the beginning of an array.
     */
    public static function prepend(array $array, mixed $value, mixed $key = null): array
    {
        if ($key === null) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }

        return $array;
    }

    /**
     * Get a value from the array, and remove it.
     */
    public static function pull(array &$array, string|int $key, mixed $default = null): mixed
    {
        $value = $array[$key] ?? $default;
        unset($array[$key]);

        return $value;
    }

    /**
     * Return an array of key-value pairs from the given array.
     */
    public static function map(array $array, callable $callback): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[$key] = $callback($value, $key);
        }

        return $result;
    }

    /**
     * Shuffle the given array and return the result.
     */
    public static function shuffle(array $array): array
    {
        shuffle($array);

        return $array;
    }

    /**
     * Conditionally compile classes from an array into a CSS class list.
     */
    public static function toCssClasses(array $array): string
    {
        $classList = [];

        foreach ($array as $class => $constraint) {
            if (is_numeric($class)) {
                $classList[] = $constraint;
            } elseif ($constraint) {
                $classList[] = $class;
            }
        }

        return implode(' ', $classList);
    }

    /**
     * Sort the array using the given callback or "dot" notation.
     */
    public static function sortBy(array $array, callable|string $callback, int $options = SORT_REGULAR, bool $descending = false): array
    {
        $results = [];

        if (is_string($callback)) {
            $key = $callback;
            $callback = fn ($item) => $item[$key] ?? null;
        }

        foreach ($array as $k => $value) {
            $results[$k] = $callback($value, $k);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        foreach (array_keys($results) as $key) {
            $results[$key] = $array[$key];
        }

        return $results;
    }

    /**
     * Filter the array using the given callback.
     */
    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filter items where the value is not null.
     */
    public static function whereNotNull(array $array): array
    {
        return self::where($array, fn ($value) => $value !== null);
    }

    /**
     * If the given value is not an array and not null, wrap it in one.
     */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Add a key/value pair to an array only when the key is absent or its
     * current value is null (Laravel parity).
     */
    public static function add(array $array, string|int $key, mixed $value): array
    {
        if (!array_key_exists($key, $array) || $array[$key] === null) {
            $array[$key] = $value;
        }

        return $array;
    }

    /**
     * Collapse an array of arrays into a single array, one level deep
     * (Laravel parity). Non-array members are skipped.
     */
    public static function collapse(array $array): array
    {
        $results = [];
        foreach ($array as $values) {
            if (!is_array($values)) {
                continue;
            }
            $results[] = $values;
        }

        return array_merge([], ...$results);
    }

    /**
     * Cross-join the given arrays, returning the cartesian product as a list
     * of tuples (Laravel parity).
     */
    public static function crossJoin(array ...$arrays): array
    {
        $results = [[]];

        foreach ($arrays as $array) {
            $append = [];
            foreach ($results as $product) {
                foreach ($array as $item) {
                    $append[] = [...$product, $item];
                }
            }
            $results = $append;
        }

        return $results;
    }

    /**
     * Split an array into two arrays — one of its keys, one of its values
     * (Laravel parity).
     *
     * @return array{0: list<array-key>, 1: list<mixed>}
     */
    public static function divide(array $array): array
    {
        return [array_keys($array), array_values($array)];
    }

    /**
     * Key the array by the given attribute name or callback. On a key
     * collision the last item wins (Laravel parity).
     */
    public static function keyBy(array $array, callable|string $keyBy): array
    {
        $resolver = is_string($keyBy)
            ? static fn ($item) => is_array($item) ? ($item[$keyBy] ?? null) : ($item->{$keyBy} ?? null)
            : $keyBy;

        $results = [];
        foreach ($array as $key => $item) {
            $results[$resolver($item, $key)] = $item;
        }

        return $results;
    }

    /**
     * Map the array, remapping both keys and values. The callback returns a
     * single key/value pair per item (Laravel parity).
     */
    public static function mapWithKeys(array $array, callable $callback): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            foreach ($callback($value, $key) as $mapKey => $mapValue) {
                $results[$mapKey] = $mapValue;
            }
        }

        return $results;
    }

    /**
     * Prepend the given prefix to every key of the array (Laravel parity).
     */
    public static function prependKeysWith(array $array, string $prefix): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            $results[$prefix . $key] = $value;
        }

        return $results;
    }

    /**
     * Take the first ($limit > 0) or last ($limit < 0) items of the array
     * (Laravel parity).
     */
    public static function take(array $array, int $limit): array
    {
        if ($limit < 0) {
            return array_slice($array, $limit);
        }

        return array_slice($array, 0, $limit);
    }
}
