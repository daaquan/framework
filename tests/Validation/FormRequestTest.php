<?php

use Phare\Http\FormRequest;
use Phare\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class TestFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'integer|min:18'
        ];
    }
    
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is absolutely required!',
            'email.email' => 'Please provide a valid email address.'
        ];
    }
    
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address'
        ];
    }
}

class UnauthorizedFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }
    
    public function rules(): array
    {
        return ['field' => 'required'];
    }
}

class FormRequestTest extends TestCase
{
    protected function mockRequest(array $data): TestFormRequest
    {
        $request = new TestFormRequest();
        $request->data = $data;
        return $request;
    }
}

it('can define validation rules', function () {
    $request = new TestFormRequest();
    $rules = $request->rules();
    
    expect($rules)->toHaveKey('name');
    expect($rules)->toHaveKey('email');
    expect($rules)->toHaveKey('age');
    expect($rules['name'])->toBe('required|string');
    expect($rules['email'])->toBe('required|email');
})->uses(FormRequestTest::class);

it('can define custom messages', function () {
    $request = new TestFormRequest();
    $messages = $request->messages();
    
    expect($messages)->toHaveKey('name.required');
    expect($messages)->toHaveKey('email.email');
    expect($messages['name.required'])->toBe('The name field is absolutely required!');
})->uses(FormRequestTest::class);

it('can define custom attributes', function () {
    $request = new TestFormRequest();
    $attributes = $request->attributes();
    
    expect($attributes)->toHaveKey('name');
    expect($attributes)->toHaveKey('email');
    expect($attributes['name'])->toBe('full name');
    expect($attributes['email'])->toBe('email address');
})->uses(FormRequestTest::class);

it('authorizes by default', function () {
    $request = new TestFormRequest();
    
    expect($request->authorize())->toBe(true);
})->uses(FormRequestTest::class);

it('can create validator instance', function () {
    $request = $this->mockRequest([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 25
    ]);
    
    $validator = $request->getValidatorInstance();
    
    expect($validator)->toBeInstanceOf(\Phare\Validation\Validator::class);
    expect($validator->passes())->toBe(true);
})->uses(FormRequestTest::class);

it('validator uses custom messages and attributes', function () {
    $request = $this->mockRequest([
        'name' => '',
        'email' => 'invalid-email'
    ]);
    
    $validator = $request->getValidatorInstance();
    
    expect($validator->fails())->toBe(true);
    expect($validator->errors()->first('name'))->toBe('The name field is absolutely required!');
    expect($validator->errors()->first('email'))->toBe('Please provide a valid email address.');
})->uses(FormRequestTest::class);

it('can get validated data', function () {
    $request = $this->mockRequest([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 25,
        'extra_field' => 'should not be included'
    ]);
    
    $validated = $request->validated();
    
    expect($validated)->toHaveKey('name');
    expect($validated)->toHaveKey('email');
    expect($validated)->toHaveKey('age');
    expect($validated)->not->toHaveKey('extra_field');
    expect($validated['name'])->toBe('John Doe');
    expect($validated['email'])->toBe('john@example.com');
    expect($validated['age'])->toBe(25);
})->uses(FormRequestTest::class);

it('safe method returns same as validated', function () {
    $request = $this->mockRequest([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com'
    ]);
    
    $validated = $request->validated();
    $safe = $request->safe();
    
    expect($safe)->toBe($validated);
})->uses(FormRequestTest::class);

it('throws validation exception for invalid data', function () {
    $request = $this->mockRequest([
        'name' => '',
        'email' => 'not-an-email',
        'age' => 15
    ]);
    
    expect(fn() => $request->validated())
        ->toThrow(ValidationException::class);
})->uses(FormRequestTest::class);

it('validates on resolution with valid data', function () {
    $request = $this->mockRequest([
        'name' => 'Valid Name',
        'email' => 'valid@example.com',
        'age' => 30
    ]);
    
    // Should not throw exception
    $request->validateResolved();
    
    expect(true)->toBe(true); // If we reach here, validation passed
})->uses(FormRequestTest::class);

it('throws validation exception on resolution with invalid data', function () {
    $request = $this->mockRequest([
        'name' => '',
        'email' => 'invalid'
    ]);
    
    expect(fn() => $request->validateResolved())
        ->toThrow(ValidationException::class);
})->uses(FormRequestTest::class);

it('throws exception for unauthorized requests', function () {
    $request = new UnauthorizedFormRequest();
    
    expect(fn() => $request->validateResolved())
        ->toThrow(ValidationException::class);
})->uses(FormRequestTest::class);

it('can handle optional fields', function () {
    $request = $this->mockRequest([
        'name' => 'John Doe',
        'email' => 'john@example.com'
        // age is optional since it's not required
    ]);
    
    $validated = $request->validated();
    
    expect($validated)->toHaveKey('name');
    expect($validated)->toHaveKey('email');
    expect($validated)->not->toHaveKey('age'); // Not provided, so not included
})->uses(FormRequestTest::class);

it('can handle nullable fields', function () {
    $request = new class extends FormRequest {
        public function rules(): array
        {
            return [
                'name' => 'required|string',
                'description' => 'nullable|string',
                'age' => 'nullable|integer'
            ];
        }
    };
    
    $request->data = [
        'name' => 'Test Name',
        'description' => null,
        'age' => ''
    ];
    
    $validator = $request->getValidatorInstance();
    
    expect($validator->passes())->toBe(true);
})->uses(FormRequestTest::class);

it('handles complex validation scenarios', function () {
    $request = new class extends FormRequest {
        public function rules(): array
        {
            return [
                'user.name' => 'required|string',
                'user.email' => 'required|email',
                'tags' => 'required|array',
                'tags.*' => 'string',
                'settings.theme' => 'required|in:light,dark',
                'settings.notifications' => 'boolean'
            ];
        }
    };
    
    $request->data = [
        'user' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ],
        'tags' => ['php', 'laravel', 'phalcon'],
        'settings' => [
            'theme' => 'dark',
            'notifications' => true
        ]
    ];
    
    $validator = $request->getValidatorInstance();
    
    expect($validator->passes())->toBe(true);
})->uses(FormRequestTest::class);