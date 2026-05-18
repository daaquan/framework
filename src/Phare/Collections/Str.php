<?php

namespace Phare\Collections;

use Phalcon\Support\HelperFactory;

/**
 * ServiceLocator implementation for helpers
 *
 * @method static string basename(string $uri, string $suffix = null)
 * @method static string decode(string $data, bool $associative = false, int $depth = 512, int $options = 0)
 * @method static string encode($data, int $options = 0, int $depth = 512)
 * @method static bool between(int $value, int $start, int $end)
 * @method static string camelize(string $text, string $delimiters = null, bool $lowerFirst = false)
 * @method static string concat(string $delimiter, string $first, string $second, string ...$arguments)
 * @method static int countVowels(string $text)
 * @method static string decapitalize(string $text, bool $upperRest = false, string $encoding = 'UTF-8')
 * @method static string decrement(string $text, string $separator = '_')
 * @method static string dirFromFile(string $file)
 * @method static string dirSeparator(string $directory)
 * @method static bool endsWith(string $haystack, string $needle, bool $ignoreCase = true)
 * @method static string firstBetween(string $text, string $start, string $end)
 * @method static string friendly(string $text, string $separator = '-', bool $lowercase = true, $replace = null)
 * @method static string humanize(string $text)
 * @method static bool includes(string $haystack, string $needle)
 * @method static string increment(string $text, string $separator = '_')
 * @method static bool isAnagram(string $first, string $second)
 * @method static bool isLower(string $text, string $encoding = 'UTF-8')
 * @method static bool isPalindrome(string $text)
 * @method static bool isUpper(string $text, string $encoding = 'UTF-8')
 * @method static string kebabCase(string $text, string $delimiters = null)
 * @method static int len(string $text, string $encoding = 'UTF-8')
 * @method static string lower(string $text, string $encoding = 'UTF-8')
 * @method static string pascalCase(string $text, string $delimiters = null)
 * @method static string prefix($text, string $prefix)
 * @method static string random(int $type = 0, int $length = 8)
 * @method static string reduceSlashes(string $text)
 * @method static bool startsWith(string $haystack, string $needle, bool $ignoreCase = true)
 * @method static string snakeCase(string $text, string $delimiters = null)
 * @method static string suffix($text, string $suffix)
 * @method static string ucwords(string $text, string $encoding = 'UTF-8')
 * @method static string uncamelize(string $text, string $delimiters = '_')
 * @method static string underscore(string $text)
 * @method static string upper(string $text, string $encoding = 'UTF-8')
 */
class Str
{
    private static self $instance;

    private HelperFactory $helper;

    protected static array $plural = [
        '/(quiz)$/i' => '$1zes',
        '/^(ox)$/i' => '$1en',
        '/([m|l])ouse$/i' => '$1ice',
        '/(matr|vert|ind)ix|ex$/i' => '$1ices',
        '/(x|ch|ss|sh)$/i' => '$1es',
        '/([^aeiouy]|qu)y$/i' => '$1ies',
        '/(hive)$/i' => '$1s',
        '/(?:([^f])fe|([lr])f)$/i' => '$1$2ves',
        '/(shea|lea|loa|thie)f$/i' => '$1ves',
        '/sis$/i' => 'ses',
        '/([ti])um$/i' => '$1a',
        '/(tomat|potat|ech|her|vet)o$/i' => '$1oes',
        '/(bu)s$/i' => '$1ses',
        '/(alias)$/i' => '$1es',
        '/(octop)us$/i' => '$1i',
        '/(ax|test)is$/i' => '$1es',
        '/(us)$/i' => '$1es',
        '/s$/i' => 's',
        '/$/' => 's',
    ];

    private function __construct()
    {
        $this->helper = new HelperFactory();
    }

    public static function __callStatic(string $name, array $arguments)
    {
        self::$instance ??= new self();
        if (method_exists(self::$instance, $name)) {
            return self::$name(...$arguments);
        }

        return self::$instance->helper->$name(...$arguments);
    }

    /**
     * Create StudlyCase string from _ separated string.
     *
     * @param string $value
     */
    public static function studly($value): string
    {
        static $studlyCache = [];

        $key = $value;

        if (isset($studlyCache[$key])) {
            return $studlyCache[$key];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return $studlyCache[$key] = str_replace(' ', '', $value);
    }

    /**
     * Append a character to a string if it doesn't already exist.
     *
     * @param string $origin
     * @param string $append
     */
    public static function append($origin, $append): string
    {
        if (substr($origin, -1) !== $append) {
            $origin .= $append;
        }

        return $origin;
    }

    /**
     * Append a string to value and place single instance of $with between.
     */
    public static function appendWith(string $origin, string $append, string $with): string
    {
        if (!empty($append)) {
            return rtrim($origin, $with) . $with . ltrim($append, $with);
        }

        return $origin;
    }

    /**
     * Convert words to url slug
     */
    public static function slug($str): string
    {
        return self::uncamelize(str_replace('\\', '', $str), '-');
    }

    public static function tableize($modelName)
    {
        $uncamelized = self::uncamelize($modelName, '_');

        return self::pluralize($uncamelized);
    }

    public static function pluralize($word)
    {
        $result = $word;

        foreach (static::$plural as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                $result = preg_replace($rule, $replacement, $word);
                break;
            }
        }

        return $result;
    }

    /**
     * Return the portion of a string after the first occurrence of a given value.
     */
    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    /**
     * Return the portion of a string after the last occurrence of a given value.
     */
    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strrpos($subject, $search);

        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    /**
     * Return the portion of a string before the first occurrence of a given value.
     */
    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    /**
     * Return the portion of a string before the last occurrence of a given value.
     */
    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strrpos($subject, $search);

        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    /**
     * Get the portion of a string between two given values.
     */
    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return self::before(self::after($subject, $from), $to);
    }

    /**
     * Convert a value to camelCase.
     */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /**
     * Determine if a given string contains a given substring.
     */
    public static function contains(string $haystack, string|array $needles, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        foreach ((array)$needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string contains all array values.
     */
    public static function containsAll(string $haystack, array $needles, bool $ignoreCase = false): bool
    {
        foreach ($needles as $needle) {
            if (!self::contains($haystack, $needle, $ignoreCase)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cap a string with a single instance of a given value.
     */
    public static function finish(string $value, string $cap): string
    {
        return preg_replace('/(?:' . preg_quote($cap, '/') . ')+$/u', '', $value) . $cap;
    }

    /**
     * Determine if a given string is a valid JSON string.
     */
    public static function isJson(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Determine if a given value is a valid UUID.
     */
    public static function isUuid(string $value): bool
    {
        return preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/iD', $value) === 1;
    }

    /**
     * Convert a string to kebab-case.
     */
    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    /**
     * Return the length of the given string.
     */
    public static function length(string $value, ?string $encoding = null): int
    {
        return mb_strlen($value, $encoding ?? 'UTF-8');
    }

    /**
     * Limit the number of characters in a string.
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    /**
     * Convert the given string to lower-case.
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Convert the given string to upper-case.
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Pad both sides of a string with another.
     */
    public static function padBoth(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_BOTH);
    }

    /**
     * Pad the left side of a string with another.
     */
    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_LEFT);
    }

    /**
     * Pad the right side of a string with another.
     */
    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_RIGHT);
    }

    /**
     * Begin a string with a single instance of a given value.
     */
    public static function start(string $value, string $prefix): string
    {
        return $prefix . preg_replace('/^(?:' . preg_quote($prefix, '/') . ')+/u', '', $value);
    }

    /**
     * Convert the given string to title case.
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Convert a string to snake_case.
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        static $snakeCache = [];

        $key = $value . $delimiter;

        if (isset($snakeCache[$key])) {
            return $snakeCache[$key];
        }

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value);
            $value = mb_strtolower($value, 'UTF-8');
        }

        return $snakeCache[$key] = $value;
    }

    /**
     * Determine if a given string starts with a given substring.
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string ends with a given substring.
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array)$needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make a string's first character uppercase.
     */
    public static function ucfirst(string $string): string
    {
        return mb_strtoupper(mb_substr($string, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($string, 1, null, 'UTF-8');
    }

    /**
     * Make a string's first character lowercase.
     */
    public static function lcfirst(string $string): string
    {
        return mb_strtolower(mb_substr($string, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($string, 1, null, 'UTF-8');
    }

    /**
     * Replace the first occurrence of a value in a string.
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Replace the last occurrence of a value in a string.
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Remove any occurrence of the given string in the subject.
     */
    public static function remove(string|array $search, string $subject, bool $caseSensitive = true): string
    {
        if ($caseSensitive) {
            return str_replace($search, '', $subject);
        }

        return str_ireplace($search, '', $subject);
    }

    /**
     * Reverse the given string.
     */
    public static function reverse(string $value): string
    {
        return implode('', array_reverse(mb_str_split($value)));
    }

    /**
     * Generate a more truly "random" alpha-numeric string.
     */
    public static function random(int $length = 16): string
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytesSize = (int)ceil($size / 3) * 3;
            $bytes = random_bytes($bytesSize);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Repeat the given string.
     */
    public static function repeat(string $string, int $times): string
    {
        return str_repeat($string, $times);
    }

    /**
     * Replace the given value in the given string.
     */
    public static function replace(string|array $search, string|array $replace, string $subject, bool $caseSensitive = true): string
    {
        return $caseSensitive
            ? str_replace($search, $replace, $subject)
            : str_ireplace($search, $replace, $subject);
    }

    /**
     * Get the number of words a string contains.
     */
    public static function wordCount(string $string, ?string $characters = null): int
    {
        return str_word_count($string, 0, $characters);
    }

    /**
     * Wrap the string with the given strings.
     */
    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before . $value . ($after ?? $before);
    }

    /**
     * Determine if a given string is empty after trimming.
     */
    public static function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    /**
     * Determine if a given string is not empty after trimming.
     */
    public static function isFilled(?string $value): bool
    {
        return !self::isBlank($value);
    }

    /**
     * Mask a portion of a string with a repeated character (Laravel parity).
     *
     * A negative $index counts from the end of the string.
     */
    public static function mask(string $string, string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): string
    {
        if ($character === '') {
            return $string;
        }

        $segment = mb_substr($string, $index, $length, $encoding);

        if ($segment === '') {
            return $string;
        }

        $strlen = mb_strlen($string, $encoding);
        $startIndex = $index < 0 ? max($strlen + $index, 0) : min($index, $strlen);

        $start = mb_substr($string, 0, $startIndex, $encoding);
        $end = mb_substr($string, $startIndex + mb_strlen($segment, $encoding), null, $encoding);

        return $start . str_repeat(mb_substr($character, 0, 1, $encoding), mb_strlen($segment, $encoding)) . $end;
    }

    /**
     * Remove leading/trailing whitespace and collapse any internal runs of
     * whitespace down to single spaces (Laravel parity).
     */
    public static function squish(string $value): string
    {
        return trim((string)preg_replace('~\s+~u', ' ', $value));
    }

    /**
     * Split a string into segments on uppercase-letter boundaries
     * (Laravel parity).
     *
     * @return list<string>
     */
    public static function ucsplit(string $string): array
    {
        return preg_split('/(?=\p{Lu})/u', $string, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Replace every occurrence of each map key with its mapped value
     * (Laravel parity).
     *
     * @param array<string, string> $map
     */
    public static function swap(array $map, string $subject): string
    {
        return strtr($subject, $map);
    }
}
