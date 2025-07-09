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
}
