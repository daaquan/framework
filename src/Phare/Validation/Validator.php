<?php

namespace Phare\Validation;

class Validator
{
    protected array $data;

    protected array $rules;

    protected array $messages;

    protected array $customAttributes;

    protected array $errors = [];

    protected array $customRules = [];

    /**
     * Rules that run even when the attribute is empty or absent.
     *
     * @var list<string>
     */
    protected array $implicitRules = [
        'required',
        'required_if',
        'required_unless',
        'required_with',
        'required_with_all',
        'required_without',
        'required_without_all',
        'filled',
        'present',
        'accepted',
        'declined',
    ];

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

        if (in_array('nullable', $rules, true) && ($value === null || $value === '')) {
            return;
        }

        foreach ($rules as $rule) {
            if (!$this->isImplicitRule($rule) && ($value === null || $value === '')) {
                continue;
            }

            $this->validateRule($attribute, $value, $rule);
        }
    }

    protected function isImplicitRule(string $rule): bool
    {
        [$name] = $this->parseRule($rule);

        return str_starts_with($name, 'required') || in_array($name, $this->implicitRules, true);
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
        } elseif ($rule !== '') {
            // Unknown/typo rule names used to silently pass, masking bugs.
            // Fail loudly everywhere except production.
            if (!$this->isProductionEnvironment()) {
                throw new \InvalidArgumentException("Unknown validation rule [{$rule}].");
            }
        }
    }

    protected function isProductionEnvironment(): bool
    {
        $env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';

        return $env === 'production' || $env === 'prod';
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
        $param0 = $parameters[0] ?? '';
        $param1 = $parameters[1] ?? '';

        $messages = [
            'required' => "The {$attribute} field is required.",
            'string' => "The {$attribute} must be a string.",
            'integer' => "The {$attribute} must be an integer.",
            'numeric' => "The {$attribute} must be a number.",
            'email' => "The {$attribute} must be a valid email address.",
            'min' => "The {$attribute} must be at least {$param0}.",
            'max' => "The {$attribute} may not be greater than {$param0}.",
            'between' => "The {$attribute} must be between {$param0} and {$param1}.",
            'in' => "The selected {$attribute} is invalid.",
            'not_in' => "The selected {$attribute} is invalid.",
            'unique' => "The {$attribute} has already been taken.",
            'exists' => "The selected {$attribute} is invalid.",
            'confirmed' => "The {$attribute} confirmation does not match.",
            'same' => "The {$attribute} and {$param0} must match.",
            'different' => "The {$attribute} and {$param0} must be different.",
            'array' => "The {$attribute} must be an array.",
            'boolean' => "The {$attribute} field must be true or false.",
            'date' => "The {$attribute} is not a valid date.",
            'url' => "The {$attribute} format is invalid.",
            'regex' => "The {$attribute} format is invalid.",
            'alpha' => "The {$attribute} must only contain letters.",
            'alpha_num' => "The {$attribute} must only contain letters and numbers.",
            'alpha_dash' => "The {$attribute} must only contain letters, numbers, dashes and underscores.",
            'digits' => "The {$attribute} must be {$param0} digits.",
            'digits_between' => "The {$attribute} must be between {$param0} and {$param1} digits.",
            'size' => "The {$attribute} must be {$param0}.",
            'starts_with' => "The {$attribute} must start with one of the following: {$param0}.",
            'ends_with' => "The {$attribute} must end with one of the following: {$param0}.",
            'uuid' => "The {$attribute} must be a valid UUID.",
            'json' => "The {$attribute} must be a valid JSON string.",
            'ip' => "The {$attribute} must be a valid IP address.",
            'ipv4' => "The {$attribute} must be a valid IPv4 address.",
            'ipv6' => "The {$attribute} must be a valid IPv6 address.",
            'lowercase' => "The {$attribute} must be lowercase.",
            'uppercase' => "The {$attribute} must be uppercase.",
            'present' => "The {$attribute} field must be present.",
            'filled' => "The {$attribute} field must have a value.",
            'accepted' => "The {$attribute} must be accepted.",
            'declined' => "The {$attribute} must be declined.",
            'gt' => "The {$attribute} must be greater than {$param0}.",
            'gte' => "The {$attribute} must be greater than or equal to {$param0}.",
            'lt' => "The {$attribute} must be less than {$param0}.",
            'lte' => "The {$attribute} must be less than or equal to {$param0}.",
            'multiple_of' => "The {$attribute} must be a multiple of {$param0}.",
            'date_format' => "The {$attribute} does not match the format {$param0}.",
            'before' => "The {$attribute} must be a date before {$param0}.",
            'after' => "The {$attribute} must be a date after {$param0}.",
            'required_if' => "The {$attribute} field is required when {$param0} is {$param1}.",
            'required_unless' => "The {$attribute} field is required unless {$param0} is in {$param1}.",
            'required_with' => "The {$attribute} field is required when {$param0} is present.",
            'required_with_all' => "The {$attribute} field is required when {$param0} is present.",
            'required_without' => "The {$attribute} field is required when {$param0} is not present.",
            'required_without_all' => "The {$attribute} field is required when none of {$param0} are present.",
            'distinct' => "The {$attribute} field has a duplicate value.",
            'in_array' => "The {$attribute} field does not exist in {$param0}.",
            'prohibited' => "The {$attribute} field is prohibited.",
            'prohibited_if' => "The {$attribute} field is prohibited when {$param0} is {$param1}.",
            'prohibited_unless' => "The {$attribute} field is prohibited unless {$param0} is in {$param1}.",
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
        // Arrays cannot be a member of a scalar allow-list.
        if (is_array($value)) {
            return false;
        }

        return $this->valueInList($value, $parameters);
    }

    protected function validateNotIn(string $attribute, $value, array $parameters): bool
    {
        if (is_array($value)) {
            return true;
        }

        return !$this->valueInList($value, $parameters);
    }

    /**
     * Compare a scalar value against a list of string parameters without
     * forcing a (string) cast on both sides, which previously broke
     * bool/null comparisons.
     *
     * @param list<string> $parameters
     */
    protected function valueInList(mixed $value, array $parameters): bool
    {
        foreach ($parameters as $parameter) {
            if ($value === $parameter) {
                return true;
            }

            // Loose, scalar-aware comparison: numeric strings match numbers,
            // but null/false do not coerce into arbitrary strings.
            if ((is_int($value) || is_float($value)) && is_numeric($parameter) && $value == $parameter) {
                return true;
            }

            if (is_string($value) && $value === $parameter) {
                return true;
            }
        }

        return false;
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
        if (!is_array($value)) {
            return false;
        }

        if (empty($parameters)) {
            return true;
        }

        return empty(array_diff_key($value, array_fill_keys($parameters, '')));
    }

    /** @param list<string> $parameters */
    protected function validateDistinct(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $strict = in_array('strict', $parameters, true);
        $ignoreCase = in_array('ignore_case', $parameters, true);

        $seen = [];
        foreach ($value as $element) {
            $element = $ignoreCase && is_string($element) ? mb_strtolower($element) : $element;

            if (in_array($element, $seen, $strict)) {
                return false;
            }

            $seen[] = $element;
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateInArray(string $attribute, mixed $value, array $parameters): bool
    {
        $other = $this->getValue(rtrim($parameters[0], '.*'));

        return is_array($other) && in_array($value, $other);
    }

    /** @param list<string> $parameters */
    protected function validateProhibited(string $attribute, mixed $value, array $parameters): bool
    {
        return !$this->validateRequired($attribute, $value, []);
    }

    /** @param list<string> $parameters */
    protected function validateProhibitedIf(string $attribute, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0]);

        if (in_array((string)$other, array_slice($parameters, 1), true)) {
            return !$this->validateRequired($attribute, $value, []);
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateProhibitedUnless(string $attribute, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0]);

        if (!in_array((string)$other, array_slice($parameters, 1), true)) {
            return !$this->validateRequired($attribute, $value, []);
        }

        return true;
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

    /** @param list<string> $parameters */
    protected function validateAlpha(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && preg_match('/^[\pL\pM]+$/u', $value) > 0;
    }

    /** @param list<string> $parameters */
    protected function validateAlphaNum(string $attribute, mixed $value, array $parameters): bool
    {
        return (is_string($value) || is_numeric($value))
            && preg_match('/^[\pL\pM\pN]+$/u', (string)$value) > 0;
    }

    /** @param list<string> $parameters */
    protected function validateAlphaDash(string $attribute, mixed $value, array $parameters): bool
    {
        return (is_string($value) || is_numeric($value))
            && preg_match('/^[\pL\pM\pN_-]+$/u', (string)$value) > 0;
    }

    /** @param list<string> $parameters */
    protected function validateDigits(string $attribute, mixed $value, array $parameters): bool
    {
        $value = (string)$value;

        return ctype_digit($value) && strlen($value) === (int)$parameters[0];
    }

    /** @param list<string> $parameters */
    protected function validateDigitsBetween(string $attribute, mixed $value, array $parameters): bool
    {
        $value = (string)$value;
        $length = strlen($value);

        return ctype_digit($value)
            && $length >= (int)$parameters[0]
            && $length <= (int)$parameters[1];
    }

    /** @param list<string> $parameters */
    protected function validateSize(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->getSize($attribute, $value) == $parameters[0];
    }

    /** @param list<string> $parameters */
    protected function validateStartsWith(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $needle) {
            if ($needle !== '' && str_starts_with((string)$value, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $parameters */
    protected function validateEndsWith(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $needle) {
            if ($needle !== '' && str_ends_with((string)$value, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $parameters */
    protected function validateUuid(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && preg_match(
            '/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/iD',
            $value
        ) > 0;
    }

    /** @param list<string> $parameters */
    protected function validateJson(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /** @param list<string> $parameters */
    protected function validateIp(string $attribute, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /** @param list<string> $parameters */
    protected function validateIpv4(string $attribute, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /** @param list<string> $parameters */
    protected function validateIpv6(string $attribute, mixed $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /** @param list<string> $parameters */
    protected function validateLowercase(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && mb_strtolower($value, 'UTF-8') === $value;
    }

    /** @param list<string> $parameters */
    protected function validateUppercase(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && mb_strtoupper($value, 'UTF-8') === $value;
    }

    /** @param list<string> $parameters */
    protected function validatePresent(string $attribute, mixed $value, array $parameters): bool
    {
        return array_key_exists($attribute, $this->data);
    }

    /** @param list<string> $parameters */
    protected function validateFilled(string $attribute, mixed $value, array $parameters): bool
    {
        if (array_key_exists($attribute, $this->data)) {
            return $this->validateRequired($attribute, $value, []);
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateAccepted(string $attribute, mixed $value, array $parameters): bool
    {
        return in_array($value, ['yes', 'on', '1', 1, true, 'true'], true);
    }

    /** @param list<string> $parameters */
    protected function validateDeclined(string $attribute, mixed $value, array $parameters): bool
    {
        return in_array($value, ['no', 'off', '0', 0, false, 'false'], true);
    }

    /** @param list<string> $parameters */
    protected function validateGt(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->getSize($attribute, $value) > $this->comparisonTarget($parameters[0]);
    }

    /** @param list<string> $parameters */
    protected function validateGte(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->getSize($attribute, $value) >= $this->comparisonTarget($parameters[0]);
    }

    /** @param list<string> $parameters */
    protected function validateLt(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->getSize($attribute, $value) < $this->comparisonTarget($parameters[0]);
    }

    /** @param list<string> $parameters */
    protected function validateLte(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->getSize($attribute, $value) <= $this->comparisonTarget($parameters[0]);
    }

    /** @param list<string> $parameters */
    protected function validateMultipleOf(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_numeric($value) || !is_numeric($parameters[0])) {
            return false;
        }

        $divisor = (float)$parameters[0];
        if ($divisor == 0.0) {
            return false;
        }

        return fmod((float)$value, $divisor) == 0.0;
    }

    /** @param list<string> $parameters */
    protected function validateDateFormat(string $attribute, mixed $value, array $parameters): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $value = (string)$value;
        foreach ($parameters as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);

            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $parameters */
    protected function validateBefore(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareDates($value, $parameters[0], '<');
    }

    /** @param list<string> $parameters */
    protected function validateAfter(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareDates($value, $parameters[0], '>');
    }

    /** @param list<string> $parameters */
    protected function validateRequiredIf(string $attribute, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0]);

        if (in_array((string)$other, array_slice($parameters, 1), true)) {
            return $this->validateRequired($attribute, $value, []);
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateRequiredUnless(string $attribute, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0]);

        if (!in_array((string)$other, array_slice($parameters, 1), true)) {
            return $this->validateRequired($attribute, $value, []);
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateRequiredWith(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $field) {
            if ($this->validateRequired($field, $this->getValue($field), [])) {
                return $this->validateRequired($attribute, $value, []);
            }
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateRequiredWithAll(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $field) {
            if (!$this->validateRequired($field, $this->getValue($field), [])) {
                return true;
            }
        }

        return $this->validateRequired($attribute, $value, []);
    }

    /** @param list<string> $parameters */
    protected function validateRequiredWithout(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $field) {
            if (!$this->validateRequired($field, $this->getValue($field), [])) {
                return $this->validateRequired($attribute, $value, []);
            }
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateRequiredWithoutAll(string $attribute, mixed $value, array $parameters): bool
    {
        foreach ($parameters as $field) {
            if ($this->validateRequired($field, $this->getValue($field), [])) {
                return true;
            }
        }

        return $this->validateRequired($attribute, $value, []);
    }

    /**
     * Resolve a comparison parameter to a size: another field's size when the
     * field exists in the data, otherwise the numeric literal.
     */
    protected function comparisonTarget(string $parameter): int|float
    {
        if (array_key_exists($parameter, $this->data)) {
            return $this->getSize($parameter, $this->data[$parameter]);
        }

        return is_numeric($parameter) ? (float)$parameter : $this->getSize($parameter, $parameter);
    }

    protected function compareDates(mixed $value, string $parameter, string $operator): bool
    {
        $left = $this->toTimestamp($value);
        $right = $this->toTimestamp(array_key_exists($parameter, $this->data) ? $this->data[$parameter] : $parameter);

        if ($left === false || $right === false) {
            return false;
        }

        return $operator === '<' ? $left < $right : $left > $right;
    }

    protected function toTimestamp(mixed $value): int|false
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return strtotime((string)$value);
    }

    protected function getSize(string $attribute, $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        if (is_array($value)) {
            return count($value);
        }

        if ($value === null) {
            return 0;
        }

        return mb_strlen((string)$value);
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
