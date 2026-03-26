<?php

namespace Phare\Eloquent\Concerns;

use Closure;
use Phare\Eloquent\Scope;

trait HasGlobalScopes
{
    /**
     * @var array<class-string, array<string, Scope|Closure>>
     */
    protected static array $globalScopes = [];

    public static function addGlobalScope($scope, $implementation = null): void
    {
        $class = static::class;
        static::$globalScopes[$class] ??= [];

        if (is_string($scope) && ($implementation instanceof Scope || $implementation instanceof Closure)) {
            static::$globalScopes[$class][$scope] = $implementation;

            return;
        }

        if ($scope instanceof Scope) {
            static::$globalScopes[$class][get_class($scope)] = $scope;

            return;
        }

        if ($scope instanceof Closure) {
            static::$globalScopes[$class][spl_object_hash($scope)] = $scope;

            return;
        }

        throw new \InvalidArgumentException('Global scope must be a scope instance, closure, or identifier with implementation.');
    }

    public static function hasGlobalScope($scope): bool
    {
        return static::getGlobalScope($scope) !== null;
    }

    /**
     * @return array<string, Scope|Closure>
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    public static function getGlobalScope($scope): Scope|Closure|null
    {
        $identifier = static::resolveGlobalScopeIdentifier($scope);

        return static::getGlobalScopes()[$identifier] ?? null;
    }

    protected static function resolveGlobalScopeIdentifier($scope): string
    {
        if ($scope instanceof Scope) {
            return get_class($scope);
        }

        if ($scope instanceof Closure) {
            return spl_object_hash($scope);
        }

        if (is_string($scope)) {
            return $scope;
        }

        throw new \InvalidArgumentException('Unable to resolve global scope identifier.');
    }
}
