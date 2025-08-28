<?php

namespace Phare\Http\Resources;

class MissingValue
{
    public static function make(): static
    {
        return new static();
    }
}

function missing(): MissingValue
{
    return new MissingValue();
}