<?php

use Phare\Validation\MessageBag;
use Phare\Validation\ValidationException;
use Phare\Validation\Validator;

it('can create validation exception with validator', function () {
    $validator = Validator::make(['name' => ''], ['name' => 'required']);
    $validator->passes(); // Trigger validation

    $exception = new ValidationException($validator);

    expect($exception)->toBeInstanceOf(ValidationException::class);
    expect($exception->getMessage())->toBe('The given data was invalid.');
    expect($exception->getValidator())->toBe($validator);
});

it('can create validation exception with custom message', function () {
    $validator = Validator::make(['email' => 'invalid'], ['email' => 'email']);
    $validator->passes(); // Trigger validation

    $exception = new ValidationException($validator, 'Custom validation message');

    expect($exception->getMessage())->toBe('Custom validation message');
});

it('can get validator from exception', function () {
    $validator = Validator::make(['age' => -5], ['age' => 'min:0']);
    $validator->passes(); // Trigger validation

    $exception = new ValidationException($validator);
    $retrievedValidator = $exception->getValidator();

    expect($retrievedValidator)->toBe($validator);
    expect($retrievedValidator->fails())->toBe(true);
});

it('can get errors from exception', function () {
    $validator = Validator::make(
        ['name' => '', 'email' => 'invalid'],
        ['name' => 'required', 'email' => 'email']
    );
    $validator->passes(); // Trigger validation

    $exception = new ValidationException($validator);
    $errors = $exception->errors();

    expect($errors)->toBeInstanceOf(MessageBag::class);
    expect($errors->has('name'))->toBe(true);
    expect($errors->has('email'))->toBe(true);
});

it('has default status code of 422', function () {
    $validator = Validator::make(['field' => ''], ['field' => 'required']);
    $validator->passes();

    $exception = new ValidationException($validator);

    expect($exception->getStatus())->toBe(422);
});

it('can set custom status code', function () {
    $validator = Validator::make(['field' => ''], ['field' => 'required']);
    $validator->passes();

    $exception = new ValidationException($validator);
    $exception->setStatus(400);

    expect($exception->getStatus())->toBe(400);
});

it('can chain status code setting', function () {
    $validator = Validator::make(['field' => ''], ['field' => 'required']);
    $validator->passes();

    $exception = new ValidationException($validator);
    $result = $exception->setStatus(400);

    expect($result)->toBe($exception);
    expect($exception->getStatus())->toBe(400);
});

it('has default error bag name', function () {
    $validator = Validator::make(['field' => ''], ['field' => 'required']);
    $validator->passes();

    $exception = new ValidationException($validator);

    expect($exception->errorBag())->toBe('default');
});

it('can create exception with custom messages using static method', function () {
    $messages = [
        'name' => ['Custom name error'],
        'email' => ['Custom email error'],
    ];

    $exception = ValidationException::withMessages($messages);

    expect($exception)->toBeInstanceOf(ValidationException::class);
    expect($exception->errors()->has('name'))->toBe(true);
    expect($exception->errors()->has('email'))->toBe(true);
    expect($exception->errors()->first('name'))->toBe('Custom name error');
    expect($exception->errors()->first('email'))->toBe('Custom email error');
});

it('static withMessages creates validator that always fails', function () {
    $exception = ValidationException::withMessages(['field' => ['Error message']]);
    $validator = $exception->getValidator();

    expect($validator->fails())->toBe(true);
    expect($validator->passes())->toBe(false);
    expect($validator->validated())->toBe([]);
    expect($validator->safe())->toBe([]);
});

it('can handle multiple validation errors', function () {
    $data = [
        'name' => '',
        'email' => 'not-an-email',
        'age' => 'not-a-number',
        'password' => '123',
    ];

    $rules = [
        'name' => 'required',
        'email' => 'required|email',
        'age' => 'required|integer|min:18',
        'password' => 'required|min:8',
    ];

    $validator = Validator::make($data, $rules);
    $validator->passes(); // Trigger validation

    $exception = new ValidationException($validator);
    $errors = $exception->errors();

    expect($errors->has('name'))->toBe(true);
    expect($errors->has('email'))->toBe(true);
    expect($errors->has('age'))->toBe(true);
    expect($errors->has('password'))->toBe(true);

    // Check specific error messages
    expect($errors->first('name'))->toContain('required');
    expect($errors->first('email'))->toContain('email');
    expect($errors->first('age'))->toContain('integer');
    expect($errors->first('password'))->toContain('8');
});

it('preserves original exception properties', function () {
    $validator = Validator::make(['field' => ''], ['field' => 'required']);
    $validator->passes();

    $previous = new \Exception('Previous exception');
    $exception = new ValidationException($validator, 'Custom message', 123, $previous);

    expect($exception->getMessage())->toBe('Custom message');
    expect($exception->getCode())->toBe(123);
    expect($exception->getPrevious())->toBe($previous);
});

it('can be thrown and caught', function () {
    $validator = Validator::make(['required_field' => ''], ['required_field' => 'required']);
    $validator->passes();

    $thrownException = null;

    try {
        throw new ValidationException($validator, 'Test validation failed');
    } catch (ValidationException $e) {
        $thrownException = $e;
    }

    expect($thrownException)->toBeInstanceOf(ValidationException::class);
    expect($thrownException->getMessage())->toBe('Test validation failed');
    expect($thrownException->errors()->has('required_field'))->toBe(true);
});
