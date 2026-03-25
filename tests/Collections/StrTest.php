<?php

use Phare\Collections\Str;

test('studly converts underscored string', function () {
    expect(Str::studly('foo_bar'))->toBe('FooBar')
        ->and(Str::studly('hello-world'))->toBe('HelloWorld');
});

test('camel converts to camelCase', function () {
    expect(Str::camel('foo_bar'))->toBe('fooBar')
        ->and(Str::camel('hello-world'))->toBe('helloWorld');
});

test('snake converts to snake_case', function () {
    expect(Str::snake('FooBar'))->toBe('foo_bar')
        ->and(Str::snake('fooBar'))->toBe('foo_bar');
});

test('kebab converts to kebab-case', function () {
    expect(Str::kebab('FooBar'))->toBe('foo-bar');
});

test('after returns substring after first occurrence', function () {
    expect(Str::after('hello world foo', ' '))->toBe('world foo')
        ->and(Str::after('hello', 'missing'))->toBe('hello')
        ->and(Str::after('hello', ''))->toBe('hello');
});

test('afterLast returns substring after last occurrence', function () {
    expect(Str::afterLast('App\\Http\\Controller', '\\'))->toBe('Controller')
        ->and(Str::afterLast('hello', 'missing'))->toBe('hello');
});

test('before returns substring before first occurrence', function () {
    expect(Str::before('hello world foo', ' '))->toBe('hello')
        ->and(Str::before('hello', 'missing'))->toBe('hello');
});

test('beforeLast returns substring before last occurrence', function () {
    expect(Str::beforeLast('App\\Http\\Controller', '\\'))->toBe('App\\Http')
        ->and(Str::beforeLast('hello', 'missing'))->toBe('hello');
});

test('between extracts string between delimiters', function () {
    expect(Str::between('[hello]', '[', ']'))->toBe('hello')
        ->and(Str::between('abc{def}ghi', '{', '}'))->toBe('def');
});

test('contains checks for substring presence', function () {
    expect(Str::contains('hello world', 'world'))->toBeTrue()
        ->and(Str::contains('hello world', 'missing'))->toBeFalse()
        ->and(Str::contains('hello world', ['world', 'missing']))->toBeTrue()
        ->and(Str::contains('hello world', ['missing1', 'missing2']))->toBeFalse();
});

test('contains is case insensitive when specified', function () {
    expect(Str::contains('Hello World', 'hello', true))->toBeTrue()
        ->and(Str::contains('Hello World', 'hello', false))->toBeFalse();
});

test('containsAll checks all substrings', function () {
    expect(Str::containsAll('hello beautiful world', ['hello', 'world']))->toBeTrue()
        ->and(Str::containsAll('hello world', ['hello', 'missing']))->toBeFalse();
});

test('startsWith checks string beginning', function () {
    expect(Str::startsWith('hello world', 'hello'))->toBeTrue()
        ->and(Str::startsWith('hello world', 'world'))->toBeFalse()
        ->and(Str::startsWith('hello world', ['foo', 'hello']))->toBeTrue();
});

test('endsWith checks string ending', function () {
    expect(Str::endsWith('hello world', 'world'))->toBeTrue()
        ->and(Str::endsWith('hello world', 'hello'))->toBeFalse()
        ->and(Str::endsWith('hello world', ['foo', 'world']))->toBeTrue();
});

test('finish caps a string', function () {
    expect(Str::finish('path/to', '/'))->toBe('path/to/')
        ->and(Str::finish('path/to/', '/'))->toBe('path/to/');
});

test('start prefixes a string', function () {
    expect(Str::start('path/to', '/'))->toBe('/path/to')
        ->and(Str::start('/path/to', '/'))->toBe('/path/to');
});

test('isJson validates JSON strings', function () {
    expect(Str::isJson('{"key":"value"}'))->toBeTrue()
        ->and(Str::isJson('[1,2,3]'))->toBeTrue()
        ->and(Str::isJson('not json'))->toBeFalse()
        ->and(Str::isJson(''))->toBeFalse();
});

test('isUuid validates UUID strings', function () {
    expect(Str::isUuid('550e8400-e29b-41d4-a716-446655440000'))->toBeTrue()
        ->and(Str::isUuid('not-a-uuid'))->toBeFalse();
});

test('length returns string length', function () {
    expect(Str::length('hello'))->toBe(5)
        ->and(Str::length('日本語'))->toBe(3);
});

test('limit truncates string', function () {
    expect(Str::limit('hello world', 5))->toBe('hello...')
        ->and(Str::limit('hi', 10))->toBe('hi');
});

test('lower converts to lowercase', function () {
    expect(Str::lower('HELLO'))->toBe('hello');
});

test('upper converts to uppercase', function () {
    expect(Str::upper('hello'))->toBe('HELLO');
});

test('title converts to title case', function () {
    expect(Str::title('hello world'))->toBe('Hello World');
});

test('ucfirst uppercases first character', function () {
    expect(Str::ucfirst('hello'))->toBe('Hello');
});

test('lcfirst lowercases first character', function () {
    expect(Str::lcfirst('Hello'))->toBe('hello');
});

test('replaceFirst replaces first occurrence', function () {
    expect(Str::replaceFirst('a', 'x', 'abcabc'))->toBe('xbcabc');
});

test('replaceLast replaces last occurrence', function () {
    expect(Str::replaceLast('a', 'x', 'abcabc'))->toBe('abcxbc');
});

test('remove strips occurrences', function () {
    expect(Str::remove('world', 'hello world'))->toBe('hello ')
        ->and(Str::remove('hello ', 'hello world'))->toBe('world');
});

test('reverse reverses string', function () {
    expect(Str::reverse('hello'))->toBe('olleh');
});

test('repeat repeats string', function () {
    expect(Str::repeat('ab', 3))->toBe('ababab');
});

test('wordCount counts words', function () {
    expect(Str::wordCount('hello beautiful world'))->toBe(3);
});

test('wrap wraps string', function () {
    expect(Str::wrap('hello', '"'))->toBe('"hello"')
        ->and(Str::wrap('hello', '<', '>'))->toBe('<hello>');
});

test('isBlank checks blank strings', function () {
    expect(Str::isBlank(''))->toBeTrue()
        ->and(Str::isBlank('  '))->toBeTrue()
        ->and(Str::isBlank(null))->toBeTrue()
        ->and(Str::isBlank('hello'))->toBeFalse();
});

test('isFilled is opposite of isBlank', function () {
    expect(Str::isFilled('hello'))->toBeTrue()
        ->and(Str::isFilled(''))->toBeFalse();
});

test('slug creates url-friendly string', function () {
    expect(Str::slug('HelloWorld'))->toBe('hello-world');
});

test('padBoth pads both sides', function () {
    expect(Str::padBoth('hi', 6, '-'))->toBe('--hi--');
});

test('padLeft pads left side', function () {
    expect(Str::padLeft('5', 3, '0'))->toBe('005');
});

test('padRight pads right side', function () {
    expect(Str::padRight('hi', 5, '.'))->toBe('hi...');
});

test('pluralize handles common cases', function () {
    expect(Str::pluralize('test'))->toBe('tests')
        ->and(Str::pluralize('quiz'))->toBe('quizzes')
        ->and(Str::pluralize('ox'))->toBe('oxen');
});

test('replace works case sensitively and insensitively', function () {
    expect(Str::replace('World', 'PHP', 'Hello World'))->toBe('Hello PHP')
        ->and(Str::replace('world', 'PHP', 'Hello World', false))->toBe('Hello PHP');
});

test('random generates string of given length', function () {
    $r = Str::random(32);
    expect(strlen($r))->toBe(32);
});
