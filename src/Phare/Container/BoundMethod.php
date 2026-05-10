<?php

namespace Phare\Container;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Laravel-parity helper for autowired invocation of Closures, method
 * arrays, and `Class@method` strings.
 *
 * Keeps {@see Container} smaller by isolating the reflection logic
 * needed to invoke arbitrary callables with dependency overrides.
 */
class BoundMethod
{
    /**
     * Invoke the callback with autowired dependencies and named overrides.
     */
    public static function call(Container $container, $callback, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            return static::callClass($container, $callback, $parameters, $defaultMethod);
        }

        if (is_array($callback) && is_object($callback[0] ?? null)) {
            return static::callBoundMethod($container, $callback, $parameters);
        }

        if ($callback instanceof Closure || is_callable($callback)) {
            return static::callClosure($container, $callback, $parameters);
        }

        throw new InvalidArgumentException('Unsupported callable passed to Container::call().');
    }

    protected static function callClass(Container $container, string $target, array $parameters, ?string $defaultMethod): mixed
    {
        [$class, $method] = array_pad(explode('@', $target, 2), 2, null);
        $method = $method ?: $defaultMethod;

        if ($method === null) {
            throw new InvalidArgumentException('Method not provided for Class@method call.');
        }

        if ($container->hasMethodBinding($target)) {
            return $container->callMethodBinding($target, $container->make($class), $parameters);
        }

        $instance = $container->make($class);

        return static::callBoundMethod($container, [$instance, $method], $parameters);
    }

    protected static function callBoundMethod(Container $container, array $callback, array $parameters): mixed
    {
        [$instance, $method] = $callback;

        $reflection = new ReflectionMethod($instance, $method);
        $args = static::resolveArguments($container, $reflection->getParameters(), $parameters);

        return $instance->$method(...$args);
    }

    protected static function callClosure(Container $container, callable $callback, array $parameters): mixed
    {
        $closure = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);
        $reflection = new ReflectionFunction($closure);
        $args = static::resolveArguments($container, $reflection->getParameters(), $parameters);

        return $closure(...$args);
    }

    /**
     * Resolve constructor / method parameters by name override, by class
     * type-hint via the container, or fall back to declared defaults.
     *
     * @param array<int, ReflectionParameter> $declared
     * @param array<string, mixed> $overrides
     * @return array<int, mixed>
     */
    protected static function resolveArguments(Container $container, array $declared, array $overrides): array
    {
        $args = [];

        foreach ($declared as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $overrides)) {
                $args[] = $overrides[$name];

                continue;
            }

            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // Inject the calling container when the parameter type
                // matches Container itself (or its base contracts).  Mirrors
                // Laravel's Container::resolveCallableDependencies special-case
                // for the dispatching container instance.
                if (
                    $typeName === Container::class
                    || $typeName === \Phare\Contracts\Foundation\Container::class
                    || is_a($container, $typeName)
                ) {
                    $args[] = $container;

                    continue;
                }

                $args[] = $container->make($typeName);

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();

                continue;
            }

            if ($param->allowsNull()) {
                $args[] = null;

                continue;
            }

            throw new InvalidArgumentException(
                "Unable to resolve parameter [\${$name}] when invoking via Container::call()."
            );
        }

        return $args;
    }
}
