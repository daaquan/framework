<?php

namespace Phare\Validation;

use Phare\Contracts\Http\Validation\Validator as ValidatorContract;

class Validator implements ValidatorContract
{
    protected array $data;
    protected array $rules;
    protected array $messages;
    protected array $customAttributes;
    protected array $errors = [];
    protected array $customRules = [];

    public function __construct(array $data, array $rules, array $messages = [], array $customAttributes = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
        $this->customAttributes = $customAttributes;
    }

    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $attribute => $rules) {
            $this->validateAttribute($attribute, $rules);
        }

        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): MessageBag
    {
        return new MessageBag($this->errors);
    }

    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this);
        }

        $validated = [];
        foreach (array_keys($this->rules) as $attribute) {
            if (array_key_exists($attribute, $this->data)) {
                $validated[$attribute] = $this->data[$attribute];
            }
        }

        return $validated;
    }

    public function safe(): array
    {
        return $this->validated();
    }

    protected function validateAttribute(string $attribute, array|string $rules): void
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        $value = $this->getValue($attribute);

        foreach ($rules as $rule) {
            $this->validateRule($attribute, $value, $rule);
        }
    }

    protected function validateRule(string $attribute, $value, string $rule): void
    {
        [$rule, $parameters] = $this->parseRule($rule);

        if ($rule === 'nullable' && ($value === null || $value === '')) {
            return;
        }

        $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));

        if (method_exists($this, $method)) {
            if (!$this->$method($attribute, $value, $parameters)) {
                $this->addError($attribute, $rule, $parameters);
            }
        } elseif (isset($this->customRules[$rule])) {
            if (!$this->customRules[$rule]($attribute, $value, $parameters)) {
                $this->addError($attribute, $rule, $parameters);
            }
        }
    }

    protected function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$rule, $parameter] = explode(':', $rule, 2);
            return [$rule, explode(',', $parameter)];
        }

        return [$rule, []];
    }

    protected function getValue(string $attribute): mixed
    {
        return $this->data[$attribute] ?? null;
    }

    protected function addError(string $attribute, string $rule, array $parameters = []): void
    {
        $message = $this->getMessage($attribute, $rule, $parameters);
        $this->errors[$attribute][] = $message;
    }

    protected function getMessage(string $attribute, string $rule, array $parameters): string
    {
        $key = "{$attribute}.{$rule}";
        
        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        if (isset($this->messages[$rule])) {
            return $this->messages[$rule];
        }

        return $this->getDefaultMessage($attribute, $rule, $parameters);
    }

    protected function getDefaultMessage(string $attribute, string $rule, array $parameters): string
    {
        $attribute = $this->getDisplayableAttribute($attribute);

        $messages = [
            'required' => "The {$attribute} field is required.",
            'string' => "The {$attribute} must be a string.",
            'integer' => "The {$attribute} must be an integer.",
            'numeric' => "The {$attribute} must be a number.",
            'email' => "The {$attribute} must be a valid email address.",
            'min' => "The {$attribute} must be at least {$parameters[0]}.",
            'max' => "The {$attribute} may not be greater than {$parameters[0]}.",
            'between' => "The {$attribute} must be between {$parameters[0]} and {$parameters[1]}.",
            'in' => "The selected {$attribute} is invalid.",
            'not_in' => "The selected {$attribute} is invalid.",
            'unique' => "The {$attribute} has already been taken.",
            'exists' => "The selected {$attribute} is invalid.",
            'confirmed' => "The {$attribute} confirmation does not match.",
            'same' => "The {$attribute} and {$parameters[0]} must match.",
            'different' => "The {$attribute} and {$parameters[0]} must be different.",
            'array' => "The {$attribute} must be an array.",
            'boolean' => "The {$attribute} field must be true or false.",
            'date' => "The {$attribute} is not a valid date.",
            'url' => "The {$attribute} format is invalid.",
            'regex' => "The {$attribute} format is invalid.",
        ];

        return $messages[$rule] ?? "The {$attribute} field is invalid.";
    }

    protected function getDisplayableAttribute(string $attribute): string
    {
        return $this->customAttributes[$attribute] ?? str_replace('_', ' ', $attribute);
    }

    // Validation rules
    protected function validateRequired(string $attribute, $value, array $parameters): bool
    {
        return !($value === null || $value === '' || (is_array($value) && empty($value)));
    }

    protected function validateString(string $attribute, $value, array $parameters): bool
    {
        return is_string($value);
    }

    protected function validateInteger(string $attribute, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateNumeric(string $attribute, $value, array $parameters): bool
    {
        return is_numeric($value);
    }

    protected function validateEmail(string $attribute, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(string $attribute, $value, array $parameters): bool
    {
        $size = $this->getSize($attribute, $value);
        return $size >= $parameters[0];
    }

    protected function validateMax(string $attribute, $value, array $parameters): bool
    {
        $size = $this->getSize($attribute, $value);
        return $size <= $parameters[0];
    }

    protected function validateBetween(string $attribute, $value, array $parameters): bool
    {
        $size = $this->getSize($attribute, $value);
        return $size >= $parameters[0] && $size <= $parameters[1];
    }

    protected function validateIn(string $attribute, $value, array $parameters): bool
    {
        return in_array((string) $value, $parameters);
    }

    protected function validateNotIn(string $attribute, $value, array $parameters): bool
    {
        return !in_array((string) $value, $parameters);
    }

    protected function validateConfirmed(string $attribute, $value, array $parameters): bool
    {
        $confirmation = $this->getValue($attribute . '_confirmation');
        return $value === $confirmation;
    }

    protected function validateSame(string $attribute, $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0]);
        return $value === $other;
    }

    protected function validateDifferent(string $attribute, $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0]);
        return $value !== $other;
    }

    protected function validateArray(string $attribute, $value, array $parameters): bool
    {
        return is_array($value);
    }

    protected function validateBoolean(string $attribute, $value, array $parameters): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }

    protected function validateDate(string $attribute, $value, array $parameters): bool
    {
        if ($value instanceof \DateTime) {
            return true;
        }

        return strtotime($value) !== false;
    }

    protected function validateUrl(string $attribute, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateRegex(string $attribute, $value, array $parameters): bool
    {
        return preg_match($parameters[0], $value) > 0;
    }

    protected function validateNullable(string $attribute, $value, array $parameters): bool
    {
        return true; // Always passes, handled in validateRule
    }

    protected function getSize(string $attribute, $value): int|float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_array($value)) {
            return count($value);
        }

        return mb_strlen($value);
    }

    public function addCustomRule(string $rule, \Closure $callback): void
    {
        $this->customRules[$rule] = $callback;
    }

    public static function make(array $data, array $rules, array $messages = [], array $customAttributes = []): self
    {
        return new static($data, $rules, $messages, $customAttributes);
    }
}