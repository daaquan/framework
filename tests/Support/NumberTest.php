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
