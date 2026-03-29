<?php

namespace Phare\Http;

use Phare\Contracts\Foundation\Application;
use Phare\Validation\ValidationException;
use Phare\Validation\Validator;

abstract class FormRequest extends Request
{
    protected ?Application $app = null;

    protected Validator $validator;

    public function __construct()
    {
        parent::__construct();
        try {
            $root = \Phare\Support\Facades\Application::getFacadeApplication();
            $this->app = $root instanceof Application ? $root : null;
        } catch (\RuntimeException) {
            $this->app = null;
        }
    }

    public function rules(): array
    {
        return [];
    }

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
            $this->failedAuthorization();
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
