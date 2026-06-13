<?php

namespace Phare\Http;

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
use Phare\Validation\Validator;

class Request extends \Phalcon\Http\Request implements \Phare\Contracts\Http\Request
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

        $this->data = $this->resolveData();
    }

    public static function make(array $data, array $rules = [], array $messages = [], array $customAttributes = []): static
    {
        $request = new static($rules);
        $request->validate($data);

        return $request;
    }

    /**
     * Build the input source, merging a JSON body when one is present.
     *
     * @return array<string, mixed>
     */
    protected function resolveData(): array
    {
        $data = $this->get();
        $data = is_array($data) ? $data : [];

        if ($this->isJson()) {
            $json = $this->getJsonRawBody(true);

            if (is_array($json)) {
                $data = array_merge($data, $json);
            }
        }

        return $data;
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function validate($data): bool
    {
        $this->messages = [];

        $validator = new Validator($data, $this->rules());

        if ($validator->passes()) {
            return true;
        }

        foreach ($validator->errors()->toArray() as $field => $messages) {
            $this->messages[$field] = [
                'field' => $field,
                'type' => $this->firstRuleName($field),
                'message' => $this->formatValidationMessage($field, (string)end($messages)),
                'messages' => array_map(
                    fn ($message) => $this->formatValidationMessage($field, (string)$message),
                    $messages
                ),
            ];
        }

        return false;
    }

    protected function firstRuleName(string $field): string
    {
        $rules = (string)($this->rules()[$field] ?? '');
        $rule = explode('|', $rules)[0] ?? 'validation';

        return explode(':', $rule)[0] ?: 'validation';
    }

    protected function formatValidationMessage(string $field, string $message): string
    {
        if (str_contains($message, "The {$field} field is required.")) {
            return "Field {$field} is required";
        }

        return $message;
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
        $value = $this->getHeader($name);

        // Phalcon returns '' (not null) for absent headers; treat that as missing.
        return $value !== '' ? $value : $default;
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

    public function url(): string
    {
        // Path without the query string, prefixed with scheme + host.
        return $this->schemeAndHost() . $this->getURI(true);
    }

    public function fullUrl(): string
    {
        // Full URI including the query string, prefixed with scheme + host.
        return $this->schemeAndHost() . $this->getURI(false);
    }

    protected function schemeAndHost(): string
    {
        $host = $this->getHttpHost();

        if ($host === '') {
            return '';
        }

        return $this->getScheme() . '://' . $host;
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

    public function route(?string $key = null, mixed $default = null): mixed
    {
        $params = $this->resolveRouteParams();

        if ($key === null) {
            return $params;
        }

        return $params[$key] ?? $default;
    }

    /**
     * Read the matched route parameters bound to the container.
     *
     * @return array<string, mixed>
     */
    protected function resolveRouteParams(): array
    {
        $app = app();

        if ($app === null || !$app->bound('routeParams')) {
            return [];
        }

        $params = app('routeParams');

        return is_array($params) ? $params : [];
    }
}
