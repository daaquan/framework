<?php

declare(strict_types=1);

namespace Phare\Support;

use NumberFormatter;

/**
 * Laravel-parity numeric formatting helpers built on PHP's intl extension.
 */
class Number
{
    /**
     * Default locale used when none is supplied.
     */
    protected static string $locale = 'en';

    /**
     * Set the default locale for subsequent formatting calls.
     */
    public static function useLocale(string $locale): void
    {
        static::$locale = $locale;
    }

    /**
     * Format a number with grouped thousands and optional precision
     * (Laravel parity).
     *
     * @param int|float $number
     */
    public static function format($number, ?int $precision = null, ?int $maxPrecision = null, ?string $locale = null): string
    {
        $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::DECIMAL);

        if ($maxPrecision !== null) {
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        } elseif ($precision !== null) {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
        }

        return $formatter->format($number);
    }

    /**
     * Return the ordinal form of the given number, e.g. 1 → "1st"
     * (Laravel parity).
     *
     * @param int|float $number
     */
    public static function ordinal($number, ?string $locale = null): string
    {
        return (new NumberFormatter($locale ?? static::$locale, NumberFormatter::ORDINAL))->format($number);
    }

    /**
     * Format the given number as a percentage. The number is treated as an
     * already-scaled percentage value, e.g. 10 → "10%" (Laravel parity).
     *
     * @param int|float $number
     */
    public static function percentage($number, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null): string
    {
        $formatter = new NumberFormatter($locale ?? static::$locale, NumberFormatter::PERCENT);

        if ($maxPrecision !== null) {
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
        } else {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);
        }

        return $formatter->format($number / 100);
    }

    /**
     * Format a byte count into a human-readable file size (Laravel parity).
     *
     * @param int|float $bytes
     */
    public static function fileSize($bytes, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $index = 0;
        for (; ($bytes / 1024) > 0.9 && ($index < count($units) - 1); $index++) {
            $bytes /= 1024;
        }

        return sprintf('%s %s', static::format($bytes, $precision, $maxPrecision, $locale), $units[$index]);
    }

    /**
     * Spell the given number out in words (Laravel parity).
     *
     * @param int|float $number
     */
    public static function spell($number, ?string $locale = null): string
    {
        return (new NumberFormatter($locale ?? static::$locale, NumberFormatter::SPELLOUT))->format($number);
    }

    /**
     * Format the given number as a currency amount (Laravel parity).
     *
     * @param int|float $number
     */
    public static function currency($number, string $in = 'USD', ?string $locale = null): string
    {
        return (new NumberFormatter($locale ?? static::$locale, NumberFormatter::CURRENCY))
            ->formatCurrency($number, $in);
    }

    /**
     * Clamp the given number between an inclusive minimum and maximum
     * (Laravel parity).
     *
     * @param int|float $number
     * @param int|float $min
     * @param int|float $max
     * @return int|float
     */
    public static function clamp($number, $min, $max)
    {
        return min(max($number, $min), $max);
    }

    /**
     * Split a 0..$to range into inclusive `[lower, upper]` pairs of width
     * $by, applying $offset to each lower bound (Laravel parity).
     *
     * @return list<array{0: int|float, 1: int|float}>
     */
    public static function pairs(int|float $to, int|float $by, int|float $offset = 1): array
    {
        $output = [];

        for ($lower = 0; $lower < $to; $lower += $by) {
            $upper = $lower + $by;
            if ($upper > $to) {
                $upper = $to;
            }
            $output[] = [$lower + $offset, $upper];
        }

        return $output;
    }

    /**
     * Render the number with long magnitude words, e.g. 1000 → "1 thousand"
     * (Laravel parity).
     *
     * @param int|float $number
     */
    public static function forHumans($number, int $precision = 0, ?int $maxPrecision = null): string
    {
        return static::summarize($number, $precision, $maxPrecision, [
            3 => ' thousand',
            6 => ' million',
            9 => ' billion',
            12 => ' trillion',
            15 => ' quadrillion',
        ]);
    }

    /**
     * Render the number with short magnitude suffixes, e.g. 1000 → "1K"
     * (Laravel parity).
     *
     * @param int|float $number
     */
    public static function abbreviate($number, int $precision = 0, ?int $maxPrecision = null): string
    {
        return static::summarize($number, $precision, $maxPrecision, [
            3 => 'K',
            6 => 'M',
            9 => 'B',
            12 => 'T',
            15 => 'Q',
        ]);
    }

    /**
     * Parse a localized numeric string into an integer (Laravel parity).
     *
     * @return int|false
     */
    public static function parseInt(string $string, ?string $locale = null)
    {
        return (new NumberFormatter($locale ?? static::$locale, NumberFormatter::DECIMAL))
            ->parse($string, NumberFormatter::TYPE_INT32);
    }

    /**
     * Parse a localized numeric string into a float (Laravel parity).
     *
     * @return float|false
     */
    public static function parseFloat(string $string, ?string $locale = null)
    {
        return (new NumberFormatter($locale ?? static::$locale, NumberFormatter::DECIMAL))
            ->parse($string, NumberFormatter::TYPE_DOUBLE);
    }

    /**
     * Reduce a number to a scaled value plus a magnitude unit.
     *
     * @param int|float $number
     * @param array<int, string> $units
     */
    protected static function summarize($number, int $precision, ?int $maxPrecision, array $units): string
    {
        if ((float)$number === 0.0) {
            return $precision > 0 ? static::format(0, $precision, $maxPrecision) : '0';
        }

        if ($number < 0) {
            return '-' . static::summarize(abs($number), $precision, $maxPrecision, $units);
        }

        if ($number >= 1e15) {
            return static::summarize($number / 1e15, $precision, $maxPrecision, $units) . end($units);
        }

        $numberExponent = (int)floor(log10((float)$number));
        $displayExponent = $numberExponent - ($numberExponent % 3);
        $number /= 10 ** $displayExponent;

        return trim(static::format($number, $precision, $maxPrecision) . ($units[$displayExponent] ?? ''));
    }
}
