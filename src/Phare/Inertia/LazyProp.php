<?php

namespace Phare\Inertia;

/**
 * A prop whose value is only resolved when explicitly requested in a
 * partial reload. Never included on a standard (full) visit.
 */
class LazyProp
{
    /** @var callable */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(): mixed
    {
        return ($this->callback)();
    }
}
