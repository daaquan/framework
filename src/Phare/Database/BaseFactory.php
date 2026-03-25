<?php

namespace Phare\Database;

use Faker\Factory;
use Faker\Generator;

abstract class BaseFactory
{
    abstract public function definition(): array;

    protected function faker(): Generator
    {
        return Factory::create();
    }
}
