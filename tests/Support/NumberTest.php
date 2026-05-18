<?php

use Phare\Support\Number;

test('format renders a grouped decimal number', function () {
    expect(Number::format(1234567))->toBe('1,234,567');
});

test('format honours an explicit precision', function () {
    expect(Number::format(1234.5, 2))->toBe('1,234.50');
});

test('ordinal returns the English ordinal form', function () {
    expect(Number::ordinal(1))->toBe('1st')
        ->and(Number::ordinal(2))->toBe('2nd')
        ->and(Number::ordinal(3))->toBe('3rd')
        ->and(Number::ordinal(11))->toBe('11th')
        ->and(Number::ordinal(21))->toBe('21st');
});

test('percentage formats a number as a percentage', function () {
    expect(Number::percentage(10))->toBe('10%')
        ->and(Number::percentage(10, 2))->toBe('10.00%');
});

test('fileSize formats bytes into human units', function () {
    expect(Number::fileSize(0))->toBe('0 B')
        ->and(Number::fileSize(1024))->toBe('1 KB')
        ->and(Number::fileSize(1024 * 1024))->toBe('1 MB')
        ->and(Number::fileSize(1024 * 1024, 2))->toBe('1.00 MB');
});

test('fileSize steps up through larger units', function () {
    expect(Number::fileSize(1024 ** 3))->toBe('1 GB')
        ->and(Number::fileSize(1024 ** 4))->toBe('1 TB');
});

test('spell writes the number out in words', function () {
    expect(Number::spell(1))->toBe('one')
        ->and(Number::spell(42))->toBe('forty-two');
});

test('currency formats a number with a currency symbol', function () {
    expect(Number::currency(1000))->toBe('$1,000.00')
        ->and(Number::currency(1234.5))->toBe('$1,234.50');
});

test('clamp keeps a number within the given bounds', function () {
    expect(Number::clamp(5, 1, 10))->toBe(5)
        ->and(Number::clamp(-3, 1, 10))->toBe(1)
        ->and(Number::clamp(99, 1, 10))->toBe(10);
});

test('pairs splits a range into inclusive pairs', function () {
    expect(Number::pairs(25, 10))->toBe([[1, 10], [11, 20], [21, 25]]);
});

test('pairs honours a custom offset', function () {
    expect(Number::pairs(20, 10, 0))->toBe([[0, 10], [10, 20]]);
});

test('forHumans renders a number with long magnitude words', function () {
    expect(Number::forHumans(1000))->toBe('1 thousand')
        ->and(Number::forHumans(1500000))->toBe('2 million')
        ->and(Number::forHumans(1500000, 1))->toBe('1.5 million');
});

test('forHumans returns small numbers unchanged', function () {
    expect(Number::forHumans(0))->toBe('0')
        ->and(Number::forHumans(42))->toBe('42');
});

test('abbreviate renders a number with short magnitude suffixes', function () {
    expect(Number::abbreviate(1000))->toBe('1K')
        ->and(Number::abbreviate(1234567, 1))->toBe('1.2M')
        ->and(Number::abbreviate(1000000000))->toBe('1B');
});

test('abbreviate handles negative numbers', function () {
    expect(Number::abbreviate(-1000))->toBe('-1K');
});

test('parseInt parses a grouped integer string', function () {
    expect(Number::parseInt('1,234'))->toBe(1234);
});

test('parseFloat parses a grouped decimal string', function () {
    expect(Number::parseFloat('1,234.5'))->toBe(1234.5);
});
