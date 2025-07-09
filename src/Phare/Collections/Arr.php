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
}
