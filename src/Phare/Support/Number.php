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
}
