<?php

// config()
if (!function_exists('config')) {
    function config(array|string|null $key = null, mixed $default = null): mixed
    {
        $config = app('config');
        if (!$config) {
            throw new \RuntimeException('Config service not registered.');
        }

        if ($key === null) {
            return $config;
        }
        if (is_array($key)) {
            return $config->set($key);
        }

        return $config->path($key, $default);
    }
}

// env()
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return \Phare\Support\Env::get($key, $default);
    }
}

// container()
if (!function_exists('container')) {
    function container(?string $alias = null): mixed
    {
        $container = \Phalcon\Di\Di::getDefault();

        return $alias ? ($container[$alias] ?? null) : $container;
    }
}

// app()
if (!function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $app = container(\Phare\Contracts\Foundation\Application::class);
        if (!$app) {
            return null;
        }

        if ($abstract === null) {
            return $app;
        }

        return $app->bound($abstract)
            ? $app[$abstract]
            : $app->make($abstract, $parameters);
    }
}

// response()
if (!function_exists('response')) {
    function response(
        array|string|null $content = null,
        \Phare\Foundation\Http\ResponseStatusCode $statusCode = \Phare\Foundation\Http\ResponseStatusCode::OK
    ): \Phare\Contracts\Http\Response {
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
    function redirect(string $location, int $statusCode = 302): \Phare\Http\Response
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
            throw new \RuntimeException("Route not found: {$name}");
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
        \Phare\Foundation\Http\ResponseStatusCode $code = \Phare\Foundation\Http\ResponseStatusCode::BAD_REQUEST
    ) {
        return response(['message' => $message], $code);
    }
}

// view()
if (!function_exists('view')) {
    function view(string $path, array $params = []): \Phare\View\Blade
    {
        app('dispatcher')?->setParameter('bladeView', $path);
        app('view')?->setVars($params);

        return app('blade');
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

// fake()
if (!function_exists('fake') && class_exists(\Faker\Factory::class)) {
    function fake(?string $locale = null): \Faker\Generator
    {
        $locale ??= config('app.faker_locale') ?? 'en_US';
        $abstract = \Faker\Generator::class . ':' . $locale;
        if (!app()->bound($abstract)) {
            app()->singleton($abstract, fn () => \Pest\Faker\fake($locale));
        }

        return app($abstract);
    }
}

// encrypter()
if (!function_exists('encrypter')) {
    function encrypter(): \Phalcon\Encryption\Crypt
    {
        return app('encrypter');
    }
}

// encrypt()
if (!function_exists('encrypt')) {
    function encrypt(string $value, ?string $key = null): string
    {
        $key ??= config('app.key');

        return encrypter()->encryptBase64($value, $key);
    }
}

// decrypt()
if (!function_exists('decrypt')) {
    function decrypt(string $value, ?string $key = null): string
    {
        $key ??= config('app.key');

        return encrypter()->decryptBase64($value, $key);
    }
}

// bcrypt()
if (!function_exists('bcrypt')) {
    function bcrypt(string $value, ?string $key = null): string
    {
        $key ??= config('app.key');

        return security()->hash($value, $key);
    }
}

// security()
if (!function_exists('security')) {
    function security(): mixed
    {
        return app('security');
    }
}

// hash()
if (!function_exists('hash')) {
    function hash(string $value, ?string $key = null): string
    {
        $key ??= config('app.key');

        // Generate a hash using encrypt()
        // Previously decrypt() was mistakenly called
        return encrypter()->encryptBase64($value, $key);
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
    function base_path($path = "")
    {
        return app()->basePath("" . $path);
    }
}
if (!function_exists('storage_path')) {
    function storage_path($path = "")
    {
        return app()->basePath("storage/" . $path);
    }
}
if (!function_exists('database_path')) {
    function database_path($path = "")
    {
        return app()->basePath("database/" . $path);
    }
}
if (!function_exists('public_path')) {
    function public_path($path = "")
    {
        return app()->basePath("public/" . $path);
    }
}
if (!function_exists('resource_path')) {
    function resource_path($path = "")
    {
        return app()->basePath("resource/" . $path);
    }
}
if (!function_exists('lang_path')) {
    function lang_path($path = "")
    {
        return app()->basePath("lang/" . $path);
    }
}
if (!function_exists('config_path')) {
    function config_path($path = "")
    {
        return app()->configPath($path);
    }
}
if (!function_exists('bootstrap_path')) {
    function bootstrap_path($path = "")
    {
        return app()->bootstrapPath($path);
    }
}

// value()
if (!function_exists('value')) {
    function value(mixed $value, ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
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
            $out = (new \Phalcon\Support\Debug\Dump([], true))->variable($x);
            echo PHP_SAPI === 'cli' ? strip_tags($out) . PHP_EOL : $out;
        }, $args);
    }
}

// collect()
if (!function_exists('collect')) {
    function collect(iterable $value = []): \Phare\Collections\Collection
    {
        if (is_array($value) || $value instanceof \Traversable) {
            return new \Phare\Collections\Collection(iterator_to_array($value), false);
        }
        throw new \InvalidArgumentException('Value must be array or Traversable');
    }
}

// str_random()
if (!function_exists('str_random')) {
    function str_random(int $length = 16): string
    {
        return \Phare\Collections\Str::random(\Phalcon\Support\Helper\Str\Random::RANDOM_ALNUM, $length);
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
            return new \Phare\Support\HigherOrderTapProxy($value);
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
            } catch (\Exception $e) {
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

// normalize_uri()
if (!function_exists('normalize_uri')) {
    function normalize_uri(string ...$uri): string
    {
        $normalized = preg_replace('#/+#', '/', '/' . implode('/', $uri));

        return rtrim($normalized, '/') ?: '/';
    }
}
