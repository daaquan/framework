<?php

namespace Phare\Http;

use Phalcon\Filter\Validation as BaseValidation;
use Phare\Foundation\Http\Validation\ValidationException;

class Request extends \Phalcon\Http\Request implements \Phare\Contracts\Http\Request, \Phare\Contracts\Http\Validation\Validator
{
    public static array $validators = [
        'required' => \Phalcon\Filter\Validation\Validator\PresenceOf::class,
        'numeric' => \Phalcon\Filter\Validation\Validator\Numericality::class,
        'alnum' => \Phalcon\Filter\Validation\Validator\Alnum::class,
        'alpha' => \Phalcon\Filter\Validation\Validator\Alpha::class,
        'confirmation' => \Phalcon\Filter\Validation\Validator\Confirmation::class,
        'creditcard' => \Phalcon\Filter\Validation\Validator\CreditCard::class,
        'digit' => \Phalcon\Filter\Validation\Validator\Digit::class,
        'exclude' => \Phalcon\Filter\Validation\Validator\ExclusionIn::class,
        'include' => \Phalcon\Filter\Validation\Validator\InclusionIn::class,
        'identical' => \Phalcon\Filter\Validation\Validator\Identical::class,
        'email' => \Phalcon\Filter\Validation\Validator\Email::class,
        'unique' => \Phalcon\Filter\Validation\Validator\Uniqueness::class,
        'callback' => \Phalcon\Filter\Validation\Validator\Callback::class,
        'length' => \Phalcon\Filter\Validation\Validator\StringLength::class,
        'between' => \Phalcon\Filter\Validation\Validator\Between::class,
        'file' => \Phalcon\Filter\Validation\Validator\File::class,
        'url' => \Phalcon\Filter\Validation\Validator\Url::class,
        'ip' => \Phalcon\Filter\Validation\Validator\Ip::class,
        'date' => \Phalcon\Filter\Validation\Validator\Date::class,
        'regex' => \Phalcon\Filter\Validation\Validator\Regex::class,
    ];

    private array $types;

    private array $messages = [];

    private mixed $data;

    public function __construct(protected array $rules = [])
    {
        $this->types = array_flip(self::$validators);

        $this->data = $this->get();
    }

    public static function make($data, $rules = [])
    {
        return (new static($rules))->validate($data);
    }

    private static function getValidator($name, $rules = [])
    {
        if (self::$validators[$name]) {
            return new self::$validators[$name]($rules);
        }
        throw new ValidationException('Invalid validation rule.');
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function validate($data): bool
    {
        $validator = new BaseValidation();
        foreach ($this->rules() as $name => $rules) {
            foreach (explode('|', $rules) as $term) {
                $rule = explode(':', $term);
                $type = array_shift($rule);
                $option = implode('', $rule);

                $validator->add($name, self::getValidator($type, compact('type', 'option')));
            }
        }

        foreach ($validator->validate($data) as $message) {
            $this->messages = [
                'field' => $message->getField(),
                'type' => $this->types[$message->getType()],
                'message' => $message->getMessage(),
            ];
        }

        return count($this->messages) === 0;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function all()
    {
        return $this->data;
    }

    public function only(array $keys)
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    public function input($name, $default = null)
    {
        return $this->data[$name] ?? $default;
    }

    public function ip()
    {
        return $this->getClientAddress();
    }

    public function header(string $name, $default = null)
    {
        return $this->getHeader($name) ?? $default;
    }

    public function headers()
    {
        return $this->getHeaders();
    }

    public function bearerToken(): ?string
    {
        $authHeader = $this->getHeader('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function url()
    {
        return $this->getURI();
    }

    public function fullUrl(): string
    {
        return $this->getURI(true);
    }

    public function has(string|array $key): bool
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                if (!$this->has($k)) {
                    return false;
                }
            }
            return true;
        }

        return isset($this->data[$key]);
    }

    public function filled(string|array $key): bool
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                if (!$this->filled($k)) {
                    return false;
                }
            }
            return true;
        }

        return $this->has($key) && !empty($this->data[$key]);
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->getQuery();
        }

        return $this->getQuery($key, null, $default);
    }

    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', ''), '/json');
    }

    public function wantsJson(): bool
    {
        return $this->isJson() || str_contains($this->header('Accept', ''), '/json');
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->getMethod()) === strtoupper($method);
    }

    public function route(?string $param = null): mixed
    {
        // This would need to be implemented based on your routing system
        // For now, return null as placeholder
        return null;
    }
}
