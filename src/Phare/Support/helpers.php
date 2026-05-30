<?php

use Faker\Factory;
use Faker\Generator;
use Phalcon\Config\Config;
use Phalcon\Di\Di;
use Phalcon\Support\Debug\Dump;
use Phalcon\Support\Helper\Str\Random;
use Phare\Broadcasting\BroadcastManager;
use Phare\Broadcasting\PendingBroadcast;
use Phare\Collections\Arr;
use Phare\Collections\Collection;
use Phare\Collections\Str;
use Phare\Contracts\Debug\ExceptionHandler;
use Phare\Contracts\Foundation\Application;
use Phare\Encryption\Encrypter;
use Phare\Events\Contracts\ShouldBroadcast;
use Phare\Foundation\Http\ResponseStatusCode;
use Phare\Hashing\HashManager;
use Phare\Http\Response;
use Phare\Support\Env;
use Phare\Support\HigherOrderTapProxy;
use Phare\View\View;

// Polyfills for PHP 8.4 functions
if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }
}

// config()
if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        $config = app('config');
        if (!$config) {
            throw new RuntimeException('Config service not registered.');
        }

        if ($key === null) {
            return $config;
        }
        if (is_array($key)) {
            foreach ($key as $path => $value) {
                config_set_path($config, (string)$path, $value);
            }

            return true;
        }

        return $config->path($key, $default);
    }
}

if (!function_exists('config_set_path')) {
    function config_set_path(Config $config, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = $config;

        while (count($segments) > 1) {
            $segment = array_shift($segments);
            $next = $current->path($segment);

            if ($next instanceof Config) {
                $current = $next;

                continue;
            }

            if (is_array($next)) {
                $next = new Config($next);
            } else {
                $next = new Config([]);
            }

            $current->set($segment, $next);
            $current = $next;
        }

        $current->set($segments[0], $value);
    }
}

// env()
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

// container()
if (!function_exists('container')) {
    function container(?string $alias = null): mixed
    {
        $container = Di::getDefault();

        return $alias ? ($container[$alias] ?? null) : $container;
    }
}

// app()
if (!function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $app = container(Application::class);
        if (!$app) {
            return null;
        }

        if ($abstract === null) {
            return $app;
        }

        if ($app->bound($abstract)) {
            if ($app instanceof ArrayAccess && isset($app[$abstract])) {
                return $app[$abstract];
            }

            return $app->make($abstract, $parameters);
        }

        return $app->make($abstract, $parameters);
    }
}

// response()
if (!function_exists('response')) {
    function response(
        $content = null,
        ResponseStatusCode $statusCode = ResponseStatusCode::OK
    ): Phare\Contracts\Http\Response {
        $response = app('response');
        if ($content === null) {
            return $response;
        }

        if (is_array($content)) {
            return $response
                ->setContentType('application/json')
                ->setStatusCode($statusCode->value)
                ->setJsonContent($content);
        }

        return $response
            ->setContentType('text/html')
            ->setStatusCode($statusCode->value)
            ->setContent($content);
    }
}

// request()
if (!function_exists('request')) {
    function request(?string $key = null, mixed $default = null): mixed
    {
        $request = app('request');
        if (!$request) {
            return null;
        }
        if ($key === null) {
            return $request;
        }

        return $request->has($key) ? $request->get($key) : $default;
    }
}

// redirect()
if (!function_exists('redirect')) {
    function redirect(string $location, int $statusCode = 302): Response
    {
        return app('response')->redirect($location, false, $statusCode);
    }
}

// route()
if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $route = app('router')?->getRouteByName($name);
        if (!$route) {
            throw new RuntimeException("Route not found: {$name}");
        }

        $path = $route->getPattern();
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", $value, $path);
        }

        return $path;
    }
}

// abort()
if (!function_exists('abort')) {
    function abort(
        string $message,
        ResponseStatusCode $code = ResponseStatusCode::BAD_REQUEST
    ) {
        return response(['message' => $message], $code);
    }
}

// view()
if (!function_exists('view')) {
    /**
     * Get the view factory, or build a renderable view (canonical Factory stack).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $mergeData
     */
    function view(?string $view = null, array $data = [], array $mergeData = []): Phare\View\Factory|View
    {
        $factory = app('view');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }
}

// asset()
if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $v = config('app.debug')
            ? filemtime(public_path("assets/$path"))
            : config('assets.version', 1);

        $url = app('url');

        return $url->get(normalize_uri("/assets/$path"), ['v' => $v]);
    }
}

// queue()
if (!function_exists('queue')) {
    function queue(): mixed
    {
        return app('queue');
    }
}

// event()
if (!function_exists('event')) {
    function event(...$args): mixed
    {
        return app('events')->dispatch(...$args);
    }
}

// report()
if (!function_exists('report')) {
    function report(Throwable|string $exception): void
    {
        if (!$exception instanceof Throwable) {
            $exception = new RuntimeException((string)$exception);
        }

        $handler = app(ExceptionHandler::class);

        if ($handler) {
            $handler->report($exception);

            return;
        }

        $logger = app('log');
        if ($logger && method_exists($logger, 'error')) {
            $logger->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}

// info()
if (!function_exists('info')) {
    function info(string $message, array $context = []): void
    {
        app('log')?->info($message, $context);
    }
}

// logger()
if (!function_exists('logger')) {
    function logger(?string $message = null, array $context = []): mixed
    {
        $logger = app('log');

        if ($message !== null) {
            $logger?->debug($message, $context);
        }

        return $logger;
    }
}

// fake()
if (!function_exists('fake') && class_exists(Factory::class)) {
    function fake(?string $locale = null): Generator
    {
        $locale ??= config('app.faker_locale') ?? 'en_US';
        $abstract = Generator::class . ':' . $locale;
        if (!app()->bound($abstract)) {
            app()->singleton($abstract, fn () => \Pest\Faker\fake($locale));
        }

        return app($abstract);
    }
}

// encrypter()
if (!function_exists('encrypter')) {
    function encrypter(): Encrypter
    {
        $encrypter = app('encrypter');

        if (!$encrypter instanceof Encrypter) {
            throw new RuntimeException('Encrypter service not registered.');
        }

        return $encrypter;
    }
}

// encrypt()
if (!function_exists('encrypt')) {
    function encrypt(mixed $value, bool $serialize = true): string
    {
        return encrypter()->encrypt($value, $serialize);
    }
}

// decrypt()
if (!function_exists('decrypt')) {
    function decrypt(string $payload, bool $unserialize = true): mixed
    {
        return encrypter()->decrypt($payload, $unserialize);
    }
}

// bcrypt()
if (!function_exists('bcrypt')) {
    /**
     * @param array<string, mixed> $options
     */
    function bcrypt(#[SensitiveParameter] string $value, array $options = []): string
    {
        $hasher = app('hash');

        if (!$hasher instanceof HashManager) {
            throw new RuntimeException('Hash service not registered.');
        }

        return $hasher->make($value, $options);
    }
}

// hashStringWithSalt()
if (!function_exists('hashStringWithSalt')) {
    function hashStringWithSalt(string $string, string $salt): string
    {
        return base_convert(crc32($salt . $string), 10, 36);
    }
}

// unhashStringWithSalt() -> Kept for compatibility although it is not very meaningful or safe
if (!function_exists('unhashStringWithSalt')) {
    function unhashStringWithSalt(string $hashedString, string $salt): ?string
    {
        $integer = base_convert($hashedString, 36, 10);
        $original = crc32($salt . $integer);
        $length = strlen($original) - strlen($salt);

        return $length > 0 ? substr($original, 0, $length) : null;
    }
}

// session()
if (!function_exists('session')) {
    function session(?string $key = null): mixed
    {
        $session = app('session');

        return $key === null ? $session : $session?->get($key);
    }
}

// Path helpers
if (!function_exists('base_path')) {
    function base_path($path = '')
    {
        return app()->basePath('' . $path);
    }
}
if (!function_exists('storage_path')) {
    function storage_path($path = '')
    {
        return app()->basePath('storage/' . $path);
    }
}
if (!function_exists('database_path')) {
    function database_path($path = '')
    {
        return app()->basePath('database/' . $path);
    }
}
if (!function_exists('public_path')) {
    function public_path($path = '')
    {
        return app()->basePath('public/' . $path);
    }
}
if (!function_exists('resource_path')) {
    function resource_path($path = '')
    {
        return app()->basePath('resource/' . $path);
    }
}
if (!function_exists('lang_path')) {
    function lang_path($path = '')
    {
        return app()->basePath('lang/' . $path);
    }
}
if (!function_exists('config_path')) {
    function config_path($path = '')
    {
        return app()->configPath($path);
    }
}
if (!function_exists('bootstrap_path')) {
    function bootstrap_path($path = '')
    {
        return app()->bootstrapPath($path);
    }
}

// value()
if (!function_exists('value')) {
    function value(mixed $value, ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

// blank()
if (!function_exists('blank')) {
    function blank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        if ($value instanceof Stringable) {
            return trim((string)$value) === '';
        }

        return empty($value);
    }
}

// filled()
if (!function_exists('filled')) {
    function filled(mixed $value): bool
    {
        return !blank($value);
    }
}

// now()
if (!function_exists('now')) {
    function now()
    {
        return app('now');
    }
}

// lang(), __()
if (!function_exists('lang')) {
    function lang(string $text, array $placeholder = []): string
    {
        return app('translate')->t($text, $placeholder);
    }
}
if (!function_exists('__')) {
    function __(string $text, array $placeholder = []): string
    {
        return lang($text, $placeholder);
    }
}

// dd(), dump()
if (!function_exists('dd')) {
    function dd(...$args): void
    {
        dump(...$args);
        exit(1);
    }
}
if (!function_exists('dump')) {
    function dump(...$args): void
    {
        array_map(static function ($x) {
            $out = (new Dump([], true))->variable($x);
            echo PHP_SAPI === 'cli' ? helpers . phpstrip_tags($out) . PHP_EOL : $out;
        }, $args);
    }
}

// collect()
if (!function_exists('collect')) {
    function collect(iterable $value = []): Collection
    {
        if (is_array($value) || $value instanceof Traversable) {
            return new Collection(iterator_to_array($value), false);
        }
        throw new InvalidArgumentException('Value must be array or Traversable');
    }
}

// str_random()
if (!function_exists('str_random')) {
    function str_random(int $length = 16): string
    {
        return Str::random(Random::RANDOM_ALNUM, $length);
    }
}

// class_basename()
if (!function_exists('class_basename')) {
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

// with()
if (!function_exists('with')) {
    function with(mixed $value, ?callable $callback = null): mixed
    {
        return $callback ? $callback($value) : $value;
    }
}

// tap()
if (!function_exists('tap')) {
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if ($callback) {
            $callback($value);
        } else {
            return new HigherOrderTapProxy($value);
        }

        return $value;
    }
}

// retry()
if (!function_exists('retry')) {
    function retry(int $times, callable $callback, int $sleep = 0, ?callable $when = null): mixed
    {
        $attempts = 0;
        do {
            try {
                return $callback();
            } catch (Exception $e) {
                $attempts++;
                if ($attempts >= $times || ($when && !$when($e))) {
                    throw $e;
                }
                if ($sleep > 0) {
                    usleep($sleep * 1000);
                }
            }
        } while ($attempts < $times);

        return null;
    }
}

// rescue()
if (!function_exists('rescue')) {
    function rescue(callable $callback, mixed $rescue = null, bool|callable $report = true): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if (value($report, $e)) {
                report($e);
            }

            return value($rescue, $e);
        }
    }
}

// broadcast()
if (!function_exists('broadcast')) {
    /**
     * Begin broadcasting an event.
     */
    function broadcast(?ShouldBroadcast $event = null): PendingBroadcast|BroadcastManager
    {
        $broadcast = app('broadcast');

        if (is_null($event)) {
            return $broadcast;
        }

        return new PendingBroadcast($broadcast, $event);
    }
}

// normalize_uri()
if (!function_exists('normalize_uri')) {
    function normalize_uri(string ...$uri): string
    {
        $normalized = preg_replace('#/+#', '/', '/' . implode('/', $uri));

        return rtrim($normalized, '/') ?: '/';
    }
}

// data_get()
if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation, with optional
     * "*" wildcard support (Laravel parity).
     *
     * @param mixed $target
     * @param string|array<string>|null $key
     * @param mixed $default
     * @return mixed
     */
    function data_get($target, $key, $default = null)
    {
        if ($key === null) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if ($segment === null) {
                return $target;
            }

            if ($segment === '*') {
                if (!is_iterable($target)) {
                    return value($default);
                }

                $result = [];
                foreach ($target as $item) {
                    $result[] = data_get($item, $key);
                }

                return in_array('*', $key, true) ? Arr::collapse($result) : $result;
            }

            if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

// data_set()
if (!function_exists('data_set')) {
    /**
     * Set an item on an array or object using "dot" notation, with optional
     * "*" wildcard support (Laravel parity).
     *
     * @param mixed $target
     * @param string|array<string> $key
     * @param mixed $value
     * @return mixed
     */
    function data_set(&$target, $key, $value, bool $overwrite = true)
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!Arr::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (Arr::accessible($target)) {
            if ($segments) {
                if (!Arr::exists($target, $segment)) {
                    $target[$segment] = [];
                }
                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !Arr::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }
                data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];
            if ($segments) {
                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }
}

// data_fill()
if (!function_exists('data_fill')) {
    /**
     * Fill in a value on an array or object using "dot" notation only when it
     * is missing (Laravel parity).
     *
     * @param mixed $target
     * @param string|array<string> $key
     * @param mixed $value
     * @return mixed
     */
    function data_fill(&$target, $key, $value)
    {
        return data_set($target, $key, $value, false);
    }
}

// data_forget()
if (!function_exists('data_forget')) {
    /**
     * Remove an item from an array or object using "dot" notation, with
     * optional "*" wildcard support (Laravel parity).
     *
     * @param mixed $target
     * @param string|array<string> $key
     * @return mixed
     */
    function data_forget(&$target, $key)
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*' && Arr::accessible($target)) {
            if ($segments) {
                foreach ($target as &$inner) {
                    data_forget($inner, $segments);
                }
            }
        } elseif (Arr::accessible($target)) {
            if ($segments && Arr::exists($target, $segment)) {
                data_forget($target[$segment], $segments);
            } else {
                Arr::forget($target, $segment);
            }
        } elseif (is_object($target) && isset($target->{$segment})) {
            if ($segments) {
                data_forget($target->{$segment}, $segments);
            } else {
                unset($target->{$segment});
            }
        }

        return $target;
    }
}

// head()
if (!function_exists('head')) {
    /**
     * Return the first element of the given array (Laravel parity).
     *
     * @param array<mixed> $array
     * @return mixed
     */
    function head(array $array)
    {
        return reset($array);
    }
}

// last()
if (!function_exists('last')) {
    /**
     * Return the last element of the given array (Laravel parity).
     *
     * @param array<mixed> $array
     * @return mixed
     */
    function last(array $array)
    {
        return end($array);
    }
}

// throw_if()
if (!function_exists('throw_if')) {
    /**
     * Throw the given exception when the condition is truthy, otherwise
     * return the condition (Laravel parity).
     *
     * @param mixed $condition
     * @param Throwable|string $exception
     * @param mixed ...$parameters
     * @return mixed
     *
     * @throws Throwable
     */
    function throw_if($condition, $exception = 'RuntimeException', ...$parameters)
    {
        if ($condition) {
            if (is_string($exception) && class_exists($exception)) {
                $exception = new $exception(...$parameters);
            }

            throw is_string($exception) ? new RuntimeException($exception) : $exception;
        }

        return $condition;
    }
}

// throw_unless()
if (!function_exists('throw_unless')) {
    /**
     * Throw the given exception when the condition is falsy, otherwise return
     * the condition (Laravel parity).
     *
     * @param mixed $condition
     * @param Throwable|string $exception
     * @param mixed ...$parameters
     * @return mixed
     *
     * @throws Throwable
     */
    function throw_unless($condition, $exception = 'RuntimeException', ...$parameters)
    {
        if (!$condition) {
            throw_if(true, $exception, ...$parameters);
        }

        return $condition;
    }
}

// e()
if (!function_exists('e')) {
    /**
     * Escape HTML special characters in a string (Laravel parity).
     *
     * @param BackedEnum|string|int|float|null $value
     */
    function e($value, bool $doubleEncode = true): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8', $doubleEncode);
    }
}

// transform()
if (!function_exists('transform')) {
    /**
     * Apply the callback to the value when it is filled, otherwise return the
     * default (resolved when callable) (Laravel parity).
     *
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    function transform($value, callable $callback, $default = null)
    {
        if (filled($value)) {
            return $callback($value);
        }

        return is_callable($default) ? $default($value) : $default;
    }
}

// object_get()
if (!function_exists('object_get')) {
    /**
     * Read a nested object property using "dot" notation (Laravel parity).
     *
     * @param mixed $object
     * @param mixed $default
     * @return mixed
     */
    function object_get($object, ?string $key, $default = null)
    {
        if ($key === null || trim($key) === '') {
            return $object;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_object($object) || !isset($object->{$segment})) {
                return value($default);
            }
            $object = $object->{$segment};
        }

        return $object;
    }
}

// str()
if (!function_exists('str')) {
    function str(?string $string = null): Phare\Collections\Stringable|string
    {
        if (is_null($string)) {
            return Str::random();
        }

        return Str::of($string);
    }
}

// preg_replace_array()
if (!function_exists('preg_replace_array')) {
    /**
     * Replace each occurrence of the pattern with the next value from the
     * replacements array, in order (Laravel parity).
     *
     * @param array<string> $replacements
     */
    function preg_replace_array(string $pattern, array $replacements, string $subject): string
    {
        return preg_replace_callback($pattern, static function () use (&$replacements) {
            return array_shift($replacements);
        }, $subject);
    }
}
