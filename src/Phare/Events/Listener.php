<?php

namespace Phare\Events;

abstract class Listener
{
    abstract public function handle($event): void;
}