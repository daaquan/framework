<?php

namespace Phare\Http;

use Phalcon\Filter\Validation as BaseValidation;
use Phalcon\Filter\Validation\Validator\Alnum;
use Phalcon\Filter\Validation\Validator\Alpha;
use Phalcon\Filter\Validation\Validator\Between;
use Phalcon\Filter\Validation\Validator\Callback;
use Phalcon\Filter\Validation\Validator\Confirmation;
use Phalcon\Filter\Validation\Validator\CreditCard;
use Phalcon\Filter\Validation\Validator\Date;
use Phalcon\Filter\Validation\Validator\Digit;
use Phalcon\Filter\Validation\Validator\Email;
use Phalcon\Filter\Validation\Validator\ExclusionIn;
use Phalcon\Filter\Validation\Validator\File;
use Phalcon\Filter\Validation\Validator\Identical;
use Phalcon\Filter\Validation\Validator\InclusionIn;
use Phalcon\Filter\Validation\Validator\Ip;
use Phalcon\Filter\Validation\Validator\Numericality;
use Phalcon\Filter\Validation\Validator\PresenceOf;
use Phalcon\Filter\Validation\Validator\Regex;
use Phalcon\Filter\Validation\Validator\StringLength;
use Phalcon\Filter\Validation\Validator\Uniqueness;
use Phalcon\Filter\Validation\Validator\Url;
use Phare\Contracts\Http\Validation\Validator;
use Phare\Foundation\Http\Validation\ValidationException;

class Request extends \Phalcon\Http\Request implements \Phare\Contracts\Http\Request, Validator
{
    use FileHelpers;

    public static array $validators = [
        'required' => PresenceOf::class,
        'numeric' => Numericality::class,
        'alnum' => Alnum::class,
        'alpha' => Alpha::class,
        'confirmation' => Confirmation::class,
        'creditcard' => CreditCard::class,
        'digit' => Digit::class,
        'exclude' => ExclusionIn::class,
        'include' => InclusionIn::class,
        'identical' => Identical::class,
        'email' => Email::class,
        'unique' => Uniqueness::class,
        'callback' => Callback::class,
        'length' => StringLength::class,
        'between' => Between::class,
        'file' => File::class,
        'url' => Url::class,
        'ip' => Ip::class,
        'date' => Date::class,
        'regex' => Regex::class,
    ];

    private array $types;

    private array $messages = [];

    protected mixed $data;

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

    public function isMethod($methods, bool $strict = true): bool
    {
        if (is_string($methods)) {
            return strtoupper($this->getMethod()) === strtoupper($methods);
        }

        if (is_array($methods)) {
            $currentMethod = strtoupper($this->getMethod());
            foreach ($methods as $method) {
                if ($currentMethod === strtoupper($method)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    public function route(?string $param = null): mixed
    {
        // This would need to be implemented based on your routing system
        // For now, return null as placeholder
        return null;
    }
}
