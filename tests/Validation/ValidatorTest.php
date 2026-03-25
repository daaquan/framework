<?php

use Phare\Validation\MessageBag;
use Phare\Validation\ValidationException;
use Phare\Validation\Validator;

// ---- Basic validation flow ----
test('validator passes with valid data', function () {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 25,
    ];
    $rules = [
        'name' => 'required|string',
        'email' => 'required|email',
        'age' => 'required|integer|min:18',
    ];

    $validator = new Validator($data, $rules);

    expect($validator->passes())->toBeTrue()
        ->and($validator->fails())->toBeFalse()
        ->and($validator->errors()->all())->toBeEmpty();
});

test('validator fails with invalid data', function () {
    $data = ['name' => '', 'email' => 'invalid-email', 'age' => 15];
    $rules = ['name' => 'required|string', 'email' => 'required|email', 'age' => 'required|integer|min:18'];

    $validator = new Validator($data, $rules);

    expect($validator->passes())->toBeFalse()
        ->and($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('name'))->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue()
        ->and($validator->errors()->has('age'))->toBeTrue();
});

// ---- Required rule ----
test('required fails on empty string', function () {
    $v = new Validator(['name' => ''], ['name' => 'required']);
    expect($v->fails())->toBeTrue();
});

test('required fails on null', function () {
    $v = new Validator(['name' => null], ['name' => 'required']);
    expect($v->fails())->toBeTrue();
});

test('required fails on empty array', function () {
    $v = Validator::make(['tags' => []], ['tags' => 'required']);
    expect($v->fails())->toBeTrue();
});

test('required fails on missing key', function () {
    $v = Validator::make([], ['name' => 'required']);
    expect($v->fails())->toBeTrue();
});

test('required passes on zero', function () {
    $v = Validator::make(['count' => 0], ['count' => 'required']);
    expect($v->passes())->toBeTrue();
});

test('required passes on valid string', function () {
    $v = new Validator(['name' => 'John'], ['name' => 'required']);
    expect($v->passes())->toBeTrue();
});

// ---- Email rule ----
test('email passes valid addresses', function (string $email) {
    $v = new Validator(['email' => $email], ['email' => 'email']);
    expect($v->passes())->toBeTrue();
})->with(['user@example.com', 'test+tag@domain.org', 'a@b.co']);

test('email fails invalid addresses', function (string $email) {
    $v = new Validator(['email' => $email], ['email' => 'email']);
    expect($v->fails())->toBeTrue();
})->with(['invalid-email', '@domain.com', 'user@']);

// ---- Min rule ----
test('min rule with numeric values', function () {
    expect(Validator::make(['age' => 17], ['age' => 'min:18'])->fails())->toBeTrue();
    expect(Validator::make(['age' => 25], ['age' => 'min:18'])->passes())->toBeTrue();
    expect(Validator::make(['age' => 18], ['age' => 'min:18'])->passes())->toBeTrue();
});

test('min rule with string length', function () {
    expect(Validator::make(['name' => 'Jo'], ['name' => 'min:3'])->fails())->toBeTrue();
    expect(Validator::make(['name' => 'John'], ['name' => 'min:3'])->passes())->toBeTrue();
});

test('min rule with array count', function () {
    expect(Validator::make(['a' => [1, 2, 3]], ['a' => 'array|min:2'])->passes())->toBeTrue();
    expect(Validator::make(['a' => [1]], ['a' => 'array|min:2'])->fails())->toBeTrue();
});

// ---- Max rule ----
test('max rule with numeric values', function () {
    expect(Validator::make(['age' => 70], ['age' => 'max:65'])->fails())->toBeTrue();
    expect(Validator::make(['age' => 60], ['age' => 'max:65'])->passes())->toBeTrue();
});

test('max rule with string length', function () {
    expect(Validator::make(['s' => 'toolong'], ['s' => 'string|max:3'])->fails())->toBeTrue();
    expect(Validator::make(['s' => 'abc'], ['s' => 'string|max:5'])->passes())->toBeTrue();
});

// ---- Between rule ----
test('between rule', function () {
    expect(Validator::make(['age' => 17], ['age' => 'between:18,65'])->fails())->toBeTrue();
    expect(Validator::make(['age' => 70], ['age' => 'between:18,65'])->fails())->toBeTrue();
    expect(Validator::make(['age' => 25], ['age' => 'between:18,65'])->passes())->toBeTrue();
});

test('between works with strings', function () {
    $v = Validator::make(['s' => 'abcde'], ['s' => 'string|between:3,10']);
    expect($v->passes())->toBeTrue();

    $v = Validator::make(['s' => 'ab'], ['s' => 'string|between:3,10']);
    expect($v->fails())->toBeTrue();
});

// ---- In rule ----
test('in passes when value in list', function () {
    $v = new Validator(['status' => 'active'], ['status' => 'in:active,inactive,pending']);
    expect($v->passes())->toBeTrue();
});

test('in fails when value not in list', function () {
    $v = new Validator(['status' => 'invalid'], ['status' => 'in:active,inactive,pending']);
    expect($v->fails())->toBeTrue();
});

// ---- Not_in rule ----
test('not_in passes when value is not in list', function () {
    $v = Validator::make(['val' => 'other'], ['val' => 'not_in:admin,root']);
    expect($v->passes())->toBeTrue();
});

test('not_in fails when value is in list', function () {
    $v = Validator::make(['val' => 'admin'], ['val' => 'not_in:admin,root']);
    expect($v->fails())->toBeTrue();
});

// ---- String rule ----
test('string passes on string values', function () {
    $v = Validator::make(['val' => 'hello'], ['val' => 'string']);
    expect($v->passes())->toBeTrue();
});

test('string fails on integer', function () {
    expect(Validator::make(['val' => 123], ['val' => 'string'])->fails())->toBeTrue();
});

test('string fails on float', function () {
    expect(Validator::make(['val' => 1.5], ['val' => 'string'])->fails())->toBeTrue();
});

test('string fails on boolean', function () {
    expect(Validator::make(['val' => true], ['val' => 'string'])->fails())->toBeTrue();
});

// ---- Integer rule ----
test('integer passes on integer values', function ($value) {
    $v = Validator::make(['val' => $value], ['val' => 'integer']);
    expect($v->passes())->toBeTrue();
})->with(['1' => 1, '0' => 0, '-5' => -5, 'string 42' => '42']);

test('integer fails on non-integer values', function ($value) {
    $v = Validator::make(['val' => $value], ['val' => 'integer']);
    expect($v->fails())->toBeTrue();
})->with(['abc', '1.5' => 1.5, '3.14']);

// ---- Array rule ----
test('array passes on arrays', function () {
    $v = Validator::make(['val' => [1, 2]], ['val' => 'array']);
    expect($v->passes())->toBeTrue();
});

test('array fails on non-arrays', function ($value) {
    $v = Validator::make(['val' => $value], ['val' => 'array']);
    expect($v->fails())->toBeTrue();
})->with(['string', '123' => 123, 'true' => true]);

// ---- Nullable rule ----
test('nullable allows null to skip subsequent rules', function () {
    $v = new Validator(['description' => null], ['description' => 'nullable|string']);
    expect($v->passes())->toBeTrue();
});

test('nullable allows empty string to skip subsequent rules', function () {
    $v = new Validator(['description' => ''], ['description' => 'nullable|string']);
    expect($v->passes())->toBeTrue();
});

test('nullable still validates present non-null values', function () {
    $v = new Validator(['description' => 'Some text'], ['description' => 'nullable|string']);
    expect($v->passes())->toBeTrue();
});

test('nullable with failing subsequent rule', function () {
    $v = Validator::make(['val' => 'ab'], ['val' => 'nullable|string|min:3']);
    expect($v->fails())->toBeTrue();
});

// ---- Confirmed rule ----
test('confirmed passes with matching confirmation', function () {
    $data = ['password' => 'secret', 'password_confirmation' => 'secret'];
    $v = new Validator($data, ['password' => 'confirmed']);
    expect($v->passes())->toBeTrue();
});

test('confirmed fails with non-matching confirmation', function () {
    $data = ['password' => 'secret', 'password_confirmation' => 'different'];
    $v = new Validator($data, ['password' => 'confirmed']);
    expect($v->fails())->toBeTrue();
});

// ---- Same and Different rules ----
test('same rule validates equality', function () {
    $v = Validator::make(['a' => 'val', 'b' => 'val'], ['a' => 'same:b']);
    expect($v->passes())->toBeTrue();
});

test('different rule validates inequality', function () {
    $v = Validator::make(['a' => 'val1', 'b' => 'val2'], ['a' => 'different:b']);
    expect($v->passes())->toBeTrue();
});

// ---- Boolean rule ----
test('boolean passes valid boolean values', function ($value) {
    $v = Validator::make(['val' => $value], ['val' => 'boolean']);
    expect($v->passes())->toBeTrue();
})->with(['true' => true, 'false' => false, '0 int' => 0, '1 int' => 1, '0', '1']);

test('boolean fails on non-boolean values', function ($value) {
    $v = Validator::make(['val' => $value], ['val' => 'boolean']);
    expect($v->fails())->toBeTrue();
})->with(['yes', 'no', '2' => 2]);

// ---- Numeric rule ----
test('numeric passes on numeric values', function ($value) {
    $v = Validator::make(['val' => $value], ['val' => 'numeric']);
    expect($v->passes())->toBeTrue();
})->with(['42' => 42, '3.14' => 3.14, '99', '1.5', '-3']);

test('numeric fails on non-numeric', function () {
    $v = Validator::make(['val' => 'abc'], ['val' => 'numeric']);
    expect($v->fails())->toBeTrue();
});

// ---- Date rule ----
test('date passes valid dates', function ($value) {
    $v = Validator::make(['val' => $value], ['val' => 'date']);
    expect($v->passes())->toBeTrue();
})->with(['2024-01-15', '2024-12-31 23:59:59', 'January 1 2024']);

test('date fails invalid dates', function () {
    $v = Validator::make(['val' => 'not-a-date'], ['val' => 'date']);
    expect($v->fails())->toBeTrue();
});

// ---- URL rule ----
test('url passes valid urls', function (string $url) {
    $v = Validator::make(['val' => $url], ['val' => 'url']);
    expect($v->passes())->toBeTrue();
})->with(['https://example.com', 'http://sub.domain.org/path?q=1', 'ftp://files.example.com']);

test('url fails invalid urls', function (string $url) {
    $v = Validator::make(['val' => $url], ['val' => 'url']);
    expect($v->fails())->toBeTrue();
})->with(['not-a-url', 'example.com']);

// ---- Custom messages ----
test('custom error messages are used', function () {
    $v = new Validator(
        ['name' => ''],
        ['name' => 'required'],
        ['name.required' => 'Name is absolutely required!']
    );
    $v->passes();

    expect($v->errors()->first('name'))->toBe('Name is absolutely required!');
});

// ---- Custom attributes ----
test('custom attributes change field name in messages', function () {
    $v = new Validator(
        ['user_name' => ''],
        ['user_name' => 'required'],
        [],
        ['user_name' => 'username']
    );
    $v->passes();

    expect($v->errors()->first('user_name'))->toContain('username');
});

// ---- validated() ----
test('validated returns only fields with rules', function () {
    $v = new Validator(
        ['name' => 'John Doe', 'email' => 'john@example.com', 'extra' => 'not included'],
        ['name' => 'required|string', 'email' => 'required|email']
    );
    $validated = $v->validated();

    expect($validated)->toHaveKeys(['name', 'email'])
        ->and($validated)->not->toHaveKey('extra')
        ->and($validated['name'])->toBe('John Doe')
        ->and($validated['email'])->toBe('john@example.com');
});

test('validated throws ValidationException on failure', function () {
    $v = new Validator(['name' => ''], ['name' => 'required']);
    $v->validated();
})->throws(ValidationException::class);

// ---- safe() ----
test('safe returns same as validated', function () {
    $v = Validator::make(['name' => 'John'], ['name' => 'required']);
    expect($v->safe())->toBe($v->validated());
});

// ---- Static make ----
test('static make method creates validator', function () {
    $v = Validator::make(['name' => 'John'], ['name' => 'required|string']);
    expect($v->passes())->toBeTrue();
});

// ---- Array-style rules ----
test('array-style rules work like pipe-separated', function () {
    $v = Validator::make(['name' => 'John'], ['name' => ['required', 'string', 'min:2']]);
    expect($v->passes())->toBeTrue();
});

// ---- Combined rules ----
test('multiple rules on one field', function () {
    $v = Validator::make(['email' => 'test@example.com'], ['email' => 'required|string|email']);
    expect($v->passes())->toBeTrue();
});

// ---- Custom rules ----
test('custom rule can be added', function () {
    $v = Validator::make(['val' => 'hello'], ['val' => 'uppercase']);
    $v->addCustomRule('uppercase', fn ($attr, $value) => $value === strtoupper($value));
    expect($v->fails())->toBeTrue();

    $v2 = Validator::make(['val' => 'HELLO'], ['val' => 'uppercase']);
    $v2->addCustomRule('uppercase', fn ($attr, $value) => $value === strtoupper($value));
    expect($v2->passes())->toBeTrue();
});

// ---- MessageBag ----
test('message bag functionality', function () {
    $bag = new MessageBag([
        'name' => ['Name is required'],
        'email' => ['Email is invalid', 'Email must be unique'],
    ]);

    expect($bag->has('name'))->toBeTrue()
        ->and($bag->has('email'))->toBeTrue()
        ->and($bag->has('age'))->toBeFalse()
        ->and($bag->first('name'))->toBe('Name is required')
        ->and($bag->first('email'))->toBe('Email is invalid')
        ->and($bag->get('email'))->toHaveCount(2)
        ->and($bag->all())->toHaveCount(3);

    $bag->add('age', 'Age must be a number');
    expect($bag->has('age'))->toBeTrue()
        ->and($bag->all())->toHaveCount(4);
});

test('message bag toArray', function () {
    $bag = new MessageBag(['name' => ['error1'], 'email' => ['error2']]);
    expect($bag->toArray())->toBe(['name' => ['error1'], 'email' => ['error2']]);
});

test('message bag isEmpty and isNotEmpty', function () {
    $empty = new MessageBag();
    expect($empty->isEmpty())->toBeTrue()
        ->and($empty->isNotEmpty())->toBeFalse();

    $nonEmpty = new MessageBag(['name' => ['error']]);
    expect($nonEmpty->isEmpty())->toBeFalse()
        ->and($nonEmpty->isNotEmpty())->toBeTrue();
});

test('default error messages are descriptive', function () {
    $v = Validator::make(['email' => 'bad'], ['email' => 'email']);
    $v->passes();
    expect($v->errors()->first('email'))->toContain('email');
});
