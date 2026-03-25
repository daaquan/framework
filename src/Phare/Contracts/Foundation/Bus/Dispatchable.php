<?php

namespace Phare\Contracts\Foundation\Bus;

trait Dispatchable
{
    public static function dispatch(...$arguments)
    {
        return static::newPendingDispatch(new static(...$arguments));
    }

    public static function dispatchIf(bool|\Closure $boolean, ...$arguments): ?PendingDispatch
    {
        if ($boolean instanceof \Closure) {
            $job = new static(...$arguments);

            return $boolean($job) ? static::newPendingDispatch($job) : null;
        }

        return $boolean ? static::newPendingDispatch(new static(...$arguments)) : null;
    }

    public static function dispatchUnless(bool|\Closure $boolean, ...$arguments): ?PendingDispatch
    {
        if ($boolean instanceof \Closure) {
            $job = new static(...$arguments);

            return !$boolean($job) ? static::newPendingDispatch($job) : null;
        }

        return !$boolean ? static::newPendingDispatch(new static(...$arguments)) : null;
    }

    public static function dispatchSync(...$arguments): mixed
    {
        $job = new static(...$arguments);

        return $job->handle();
    }

    protected static function newPendingDispatch(mixed $job): PendingDispatch
    {
        return new PendingDispatch($job);
    }
}
