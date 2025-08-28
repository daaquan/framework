<?php

use Phare\Validation\MessageBag;
use Phare\Validation\ValidationException;
use Phare\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function test_validator_passes_with_valid_data()
    {
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

        $this->assertTrue($validator->passes());
        $this->assertFalse($validator->fails());
        $this->assertEmpty($validator->errors()->all());
    }

    public function test_validator_fails_with_invalid_data()
    {
        $data = [
            'name' => '',
            'email' => 'invalid-email',
            'age' => 15,
        ];

        $rules = [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'required|integer|min:18',
        ];

        $validator = new Validator($data, $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->fails());

        $errors = $validator->errors();
        $this->assertTrue($errors->has('name'));
        $this->assertTrue($errors->has('email'));
        $this->assertTrue($errors->has('age'));
    }

    public function test_validator_required_rule()
    {
        $validator = new Validator(['name' => ''], ['name' => 'required']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['name' => null], ['name' => 'required']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['name' => 'John'], ['name' => 'required']);
        $this->assertTrue($validator->passes());
    }

    public function test_validator_email_rule()
    {
        $validator = new Validator(['email' => 'invalid-email'], ['email' => 'email']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertTrue($validator->passes());
    }

    public function test_validator_min_rule()
    {
        $validator = new Validator(['age' => 17], ['age' => 'min:18']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['age' => 25], ['age' => 'min:18']);
        $this->assertTrue($validator->passes());

        $validator = new Validator(['name' => 'Jo'], ['name' => 'min:3']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['name' => 'John'], ['name' => 'min:3']);
        $this->assertTrue($validator->passes());
    }

    public function test_validator_max_rule()
    {
        $validator = new Validator(['age' => 70], ['age' => 'max:65']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['age' => 60], ['age' => 'max:65']);
        $this->assertTrue($validator->passes());
    }

    public function test_validator_between_rule()
    {
        $validator = new Validator(['age' => 17], ['age' => 'between:18,65']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['age' => 70], ['age' => 'between:18,65']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['age' => 25], ['age' => 'between:18,65']);
        $this->assertTrue($validator->passes());
    }

    public function test_validator_in_rule()
    {
        $validator = new Validator(['status' => 'invalid'], ['status' => 'in:active,inactive,pending']);
        $this->assertTrue($validator->fails());

        $validator = new Validator(['status' => 'active'], ['status' => 'in:active,inactive,pending']);
        $this->assertTrue($validator->passes());
    }

    public function test_validator_confirmed_rule()
    {
        $data = [
            'password' => 'secret',
            'password_confirmation' => 'different',
        ];
        $validator = new Validator($data, ['password' => 'confirmed']);
        $this->assertTrue($validator->fails());

        $data = [
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ];
        $validator = new Validator($data, ['password' => 'confirmed']);
        $this->assertTrue($validator->passes());
    }

    public function test_validator_nullable_rule()
    {
        $validator = new Validator(['description' => null], ['description' => 'nullable|string']);
        $this->assertTrue($validator->passes());

        $validator = new Validator(['description' => ''], ['description' => 'nullable|string']);
        $this->assertTrue($validator->passes());

        $validator = new Validator(['description' => 'Some text'], ['description' => 'nullable|string']);
        $this->assertTrue($validator->passes());
    }

    public function test_validator_custom_messages()
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];
        $messages = ['name.required' => 'Name is absolutely required!'];

        $validator = new Validator($data, $rules, $messages);
        $validator->passes(); // Trigger validation

        $this->assertEquals('Name is absolutely required!', $validator->errors()->first('name'));
    }

    public function test_validator_custom_attributes()
    {
        $data = ['user_name' => ''];
        $rules = ['user_name' => 'required'];
        $attributes = ['user_name' => 'username'];

        $validator = new Validator($data, $rules, [], $attributes);
        $validator->passes(); // Trigger validation

        $this->assertStringContains('username', $validator->errors()->first('user_name'));
    }

    public function test_validator_validated_method()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'extra' => 'should not be included',
        ];

        $rules = [
            'name' => 'required|string',
            'email' => 'required|email',
        ];

        $validator = new Validator($data, $rules);
        $validated = $validator->validated();

        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
        $this->assertEquals('John Doe', $validated['name']);
        $this->assertEquals('john@example.com', $validated['email']);
    }

    public function test_validation_exception_is_thrown_on_invalid_data()
    {
        $this->expectException(ValidationException::class);

        $data = ['name' => ''];
        $rules = ['name' => 'required'];
        $validator = new Validator($data, $rules);

        $validator->validated(); // Should throw ValidationException
    }

    public function test_message_bag_functionality()
    {
        $messages = new MessageBag([
            'name' => ['Name is required'],
            'email' => ['Email is invalid', 'Email must be unique'],
        ]);

        $this->assertTrue($messages->has('name'));
        $this->assertTrue($messages->has('email'));
        $this->assertFalse($messages->has('age'));

        $this->assertEquals('Name is required', $messages->first('name'));
        $this->assertEquals('Email is invalid', $messages->first('email'));

        $this->assertCount(2, $messages->get('email'));
        $this->assertCount(3, $messages->all());

        $messages->add('age', 'Age must be a number');
        $this->assertTrue($messages->has('age'));
        $this->assertCount(4, $messages->all());
    }

    public function test_static_make_method()
    {
        $validator = Validator::make(
            ['name' => 'John'],
            ['name' => 'required|string']
        );

        $this->assertTrue($validator->passes());
    }
}
