<?php

namespace Phare\Contracts\Foundation\Bus;

use Phare\Contracts\Queue\ShouldQueue;

class PendingDispatch
{
    public function __construct(protected ShouldQueue $job) {}

    public function resolve()
    {
        app('queue')->put(serialize(['retry' => 0, 'closure' => $this->job]));

        return $this;
    }
}
