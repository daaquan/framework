<?php

namespace Phare\Http;

use Phare\Validation\Validator;
use Phare\Validation\ValidationException;
use Phare\Contracts\Foundation\Application;

abstract class FormRequest extends Request
{
    protected Application $app;
    protected Validator $validator;

    public function __construct()
    {
        parent::__construct();
        $this->app = \Phare\Support\Facades\Application::getFacadeRoot();
    }

    abstract public function rules(): array;

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function validated(): array
    {
        return $this->validator()->validated();
    }

    public function safe(): array
    {
        return $this->validated();
    }

    public function validator(): Validator
    {
        if (!isset($this->validator)) {
            $this->validator = $this->createValidator();
        }

        return $this->validator;
    }

    public function validateResolved(): void
    {
        if (!$this->authorize()) {
            throw new \Phare\Foundation\Http\Validation\ValidationException(
                Validator::make([], []), 
                'This action is unauthorized.', 
                403
            );
        }

        $validator = $this->validator();

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    protected function createValidator(): Validator
    {
        $data = $this->all();
        $rules = $this->rules();
        $messages = $this->messages();
        $attributes = $this->attributes();

        return Validator::make($data, $rules, $messages, $attributes);
    }

    protected function prepareForValidation(): void
    {
        // Override in subclasses to modify data before validation
    }

    protected function passedValidation(): void
    {
        // Override in subclasses to do something after validation passes
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator);
    }

    protected function failedAuthorization(): void
    {
        throw new ValidationException(
            Validator::make([], []),
            'This action is unauthorized.',
            403
        );
    }

    public function getValidatorInstance(): Validator
    {
        $this->prepareForValidation();

        return $this->createValidator();
    }
}