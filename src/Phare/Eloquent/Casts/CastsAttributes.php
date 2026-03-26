<?php

namespace Phare\Eloquent\Casts;

interface CastsAttributes
{
    public function get($model, string $key, $value, array $attributes);

    public function set($model, string $key, $value, array $attributes);
}
