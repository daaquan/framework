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

    public function bearerToken()
    {
        $authHeader = $this->getHeader('Authorization');
        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

    }

    public function url()
    {
        return $this->getURI();
    }

    public function fullUrl()
    {
        return $this->getURI(true);
    }
}
