<?php

namespace Phare\Contracts\Foundation\Bus;

trait Dispatchable
{
    public static function dispatch(...$arguments)
    {
        return (new PendingDispatch(new static(...$arguments)))->resolve();
    }
}
