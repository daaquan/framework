<?php

namespace Phare\Contracts\Queue;

interface ShouldQueue
{
    public function handle(): void;
}
