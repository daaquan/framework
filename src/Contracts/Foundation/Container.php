<?php

namespace Phare\Contracts\Foundation;

interface Container
{
    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool;

    /**
     * Determine if the given abstract type has been resolved.
     */
    public function resolved(string $abstract): bool;

    /**
     * Determine if a given type is shared.
     */
    public function isShared(string $abstract): bool;

    /**
     * Register a binding with the container.
     *
     * @param mixed $concrete
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void;

    /**
     * Register a binding if it hasn't already been registered.
     *
     * @param mixed $concrete
     */
    public function bindIf(string $abstract, $concrete = null, bool $shared = false): void;

    /**
     * Register a shared binding in the container.
     *
     * @param mixed $concrete
     */
    public function singleton(string $abstract, $concrete = null): void;

    /**
     * Register a shared binding if it hasn't already been registered.
     *
     * @param mixed $concrete
     */
    public function singletonIf(string $abstract, $concrete = null): void;

    /**
     * Register a binding alias.
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Resolve the given type from the container.
     *
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []);
}
