<?php

use Phare\Http\FormRequest;
use Phare\Validation\ValidationException;

class TestFormRequest extends FormRequest
{
    public function __construct(private array $payload = [])
    {
        parent::__construct();
    }

    public function all()
    {
        return $this->payload;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'integer|min:18',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is absolutely required!',
            'email.email' => 'Please provide a valid email address.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
        ];
    }
}

class UnauthorizedFormRequest extends FormRequest
{
    public function all()
    {
        return [];
    }

    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return ['field' => 'required'];
    }
}

it('exposes rules messages and attributes', function () {
    $request = new TestFormRequest();

    expect($request->rules())->toHaveKey('name');
    expect($request->messages()['name.required'])->toBe('The name field is absolutely required!');
    expect($request->attributes()['email'])->toBe('email address');
    expect($request->authorize())->toBeTrue();
});

it('creates validator instance and validates payload', function () {
    $request = new TestFormRequest([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 25,
    ]);

    $validator = $request->getValidatorInstance();
    expect($validator->passes())->toBeTrue();
});

it('uses custom messages on validation failure', function () {
    $request = new TestFormRequest([
        'name' => '',
        'email' => 'invalid-email',
    ]);

    $validator = $request->getValidatorInstance();
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('name'))->toBe('The name field is absolutely required!');
    expect($validator->errors()->first('email'))->toBe('Please provide a valid email address.');
});

it('returns validated and safe data', function () {
    $request = new TestFormRequest([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'age' => 28,
        'extra' => 'ignored',
    ]);

    $validated = $request->validated();
    expect($validated)->toHaveKey('name');
    expect($validated)->toHaveKey('email');
    expect($validated)->toHaveKey('age');
    expect($validated)->not->toHaveKey('extra');
    expect($request->safe())->toBe($validated);
});

it('throws validation exception when validated data is invalid', function () {
    $request = new TestFormRequest([
        'name' => '',
        'email' => 'bad',
    ]);

    expect(fn () => $request->validated())->toThrow(ValidationException::class);
});

it('validateResolved throws for unauthorized requests', function () {
    $request = new UnauthorizedFormRequest();

    expect(fn () => $request->validateResolved())->toThrow(\Phare\Validation\ValidationException::class);
});

it('handles nullable fields in custom form request', function () {
    $request = new class([
        'name' => 'Test Name',
        'description' => null,
        'age' => '',
    ]) extends FormRequest
    {
        public function __construct(private array $payload = [])
        {
            parent::__construct();
        }

        public function all()
        {
            return $this->payload;
        }

        public function rules(): array
        {
            return [
                'name' => 'required|string',
                'description' => 'nullable|string',
                'age' => 'nullable|integer',
            ];
        }
    };

    expect($request->getValidatorInstance()->passes())->toBeTrue();
});
