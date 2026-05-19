<?php

use Phare\Collections\Str;
use Phare\Collections\Stringable;

test('Str::of returns a Stringable wrapper', function () {
    expect(Str::of('hello'))->toBeInstanceOf(Stringable::class);
});

test('the str() helper returns a Stringable wrapper', function () {
    expect(str('hello'))->toBeInstanceOf(Stringable::class)
        ->and((string)str('hello'))->toBe('hello');
});

test('Stringable casts to its underlying string', function () {
    expect((string)new Stringable('world'))->toBe('world')
        ->and((new Stringable('world'))->toString())->toBe('world');
});

test('upper and lower transform case', function () {
    expect((string)Str::of('Hello')->upper())->toBe('HELLO')
        ->and((string)Str::of('Hello')->lower())->toBe('hello');
});

test('trim strips surrounding whitespace', function () {
    expect((string)Str::of('  spaced  ')->trim())->toBe('spaced');
});

test('append and prepend extend the string', function () {
    expect((string)Str::of('b')->append('c', 'd')->prepend('a'))->toBe('abcd');
});

test('replace swaps substrings', function () {
    expect((string)Str::of('cat sat')->replace('at', 'og'))->toBe('cog sog');
});

test('chained transformations compose left to right', function () {
    expect((string)Str::of('  Hello World  ')->trim()->lower()->replace(' ', '-'))
        ->toBe('hello-world');
});

test('length returns the character count', function () {
    expect(Str::of('hello')->length())->toBe(5);
});

test('contains, startsWith and endsWith return booleans', function () {
    $value = Str::of('hello world');

    expect($value->contains('world'))->toBeTrue()
        ->and($value->startsWith('hello'))->toBeTrue()
        ->and($value->endsWith('world'))->toBeTrue()
        ->and($value->contains('xyz'))->toBeFalse();
});

test('isEmpty and isNotEmpty reflect the string state', function () {
    expect(Str::of('')->isEmpty())->toBeTrue()
        ->and(Str::of('x')->isNotEmpty())->toBeTrue();
});

test('when applies the callback only when the condition is truthy', function () {
    expect((string)Str::of('hello')->when(true, fn ($s) => $s->upper()))->toBe('HELLO')
        ->and((string)Str::of('hello')->when(false, fn ($s) => $s->upper()))->toBe('hello');
});

test('pipe hands the stringable to the callback', function () {
    expect(Str::of('hello')->pipe(fn ($s) => $s->length()))->toBe(5);
});

test('tap inspects without altering the value', function () {
    $seen = null;
    $result = Str::of('hello')->tap(function ($s) use (&$seen) {
        $seen = (string)$s;
    });

    expect($seen)->toBe('hello')
        ->and((string)$result)->toBe('hello');
});
