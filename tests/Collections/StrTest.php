<?php

use Phare\Collections\Str;

it('converts string to StudlyCase', function () {
    expect(Str::studly('foo_bar-baz'))->toBe('FooBarBaz');
});

it('appends character only when missing', function () {
    expect(Str::append('path', '/'))->toBe('path/');
    expect(Str::append('path/', '/'))->toBe('path/');
});

it('appends string with delimiter', function () {
    expect(Str::appendWith('path', 'to', '/'))->toBe('path/to');
    expect(Str::appendWith('path/', '/to', '/'))->toBe('path/to');
    expect(Str::appendWith('path', '', '/'))->toBe('path');
});

it('creates slug from class name', function () {
    expect(Str::slug('MyAwesomeClass'))->toBe('my-awesome-class');
});

it('tableizes model name', function () {
    expect(Str::tableize('UserProfile'))->toBe('user_profiles');
});

it('pluralizes common words', function () {
    expect(Str::pluralize('quiz'))->toBe('quizzes');
    expect(Str::pluralize('octopus'))->toBe('octopi');
});
