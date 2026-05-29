<?php

use Phare\Support\Traits\Macroable;

afterEach(function () {
    // Macros are static; reset between tests via a fresh anon class each test.
});

test('macro registers a dynamic instance method bound to $this', function () {
    $object = new class()
    {
        use Macroable;

        public int $base = 10;
    };

    $object::macro('plus', function (int $n) {
        return $this->base + $n;
    });

    expect($object->plus(5))->toBe(15);
    $object::flushMacros();
});

test('hasMacro reports registration state', function () {
    $object = new class()
    {
        use Macroable;
    };

    $object::macro('known', function () {
        return true;
    });

    expect($object::hasMacro('known'))->toBeTrue();
    expect($object::hasMacro('unknown'))->toBeFalse();
    $object::flushMacros();
});

test('calling an unknown method throws BadMethodCallException', function () {
    $object = new class()
    {
        use Macroable;
    };

    expect(fn () => $object->missing())->toThrow(BadMethodCallException::class);
});

test('static macro calls are supported', function () {
    $class = new class()
    {
        use Macroable;
    };

    $class::macro('answer', function () {
        return 42;
    });

    expect($class::answer())->toBe(42);
    $class::flushMacros();
});
